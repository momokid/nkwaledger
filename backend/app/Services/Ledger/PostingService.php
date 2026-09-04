<?php

namespace App\Services\Ledger;

use App\Enums\MovementReason;
use App\Exceptions\Ledger\PostingFailed;
use App\Models\AccountingPeriod;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Models\FarmUnitStockMovement;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class PostingService
{
    private const REFERENCE_ATTEMPTS = 5;

    public function post(PostingRequest $request): Transaction
    {
        $existing = $this->alreadyPosted($request);

        if ($existing !== null) {
            return $existing;
        }

        $template = $this->resolveTemplate($request);
        $amountMinor = $this->resolveAmount($request);
        $period = $this->resolvePeriod($request);
        $farmUnit = $this->resolveFarmUnit($request, $template);
        $settlementAccountId = $this->resolveSettlementAccount($request, $template);
        $loss = $this->resolveQuantityLost($request, $template, $farmUnit);
        $sale = $this->resolveQuantitySold($request, $template, $farmUnit);

        return $this->write($request, $template, $period, $farmUnit, $settlementAccountId, $amountMinor, $loss, $sale);
    }

    private function resolveQuantityLost(PostingRequest $request, TransactionTemplate $template, ?FarmUnit $farmUnit): ?array
    {
        if ($template->transaction_type !== Transaction::LOSS) {
            return null;
        }

        if ($request->quantityLost === null || trim($request->quantityLost) === '') {
            throw PostingFailed::because('Please say how many were lost.');
        }

        if (! is_numeric($request->quantityLost) || (float) $request->quantityLost <= 0) {
            throw PostingFailed::because('The number lost needs to be more than zero.');
        }

        $stocks = $this->activeStocks($farmUnit);

        if ($stocks->isEmpty()) {
            throw PostingFailed::because('There is no live stock on record to take this loss from.');
        }

        $totalOnHand = (float) $stocks->sum('current_quantity');
        $requested = (float) $request->quantityLost;

        if ($requested > $totalOnHand) {
            throw PostingFailed::because('That is more than the farm has on record. Please check the number.');
        }

        return [
            'quantity' => $request->quantityLost,
            'allocations' => $this->splitProportionally($stocks, $requested, $totalOnHand),
        ];
    }

    private function resolveQuantitySold(PostingRequest $request, TransactionTemplate $template, ?FarmUnit $farmUnit): ?array
    {
        if (! $template->is_produce_sale) {
            return null;
        }

        if ($request->quantitySold === null || trim($request->quantitySold) === '') {
            throw PostingFailed::because('Please say how many were sold.');
        }

        if (! is_numeric($request->quantitySold) || (float) $request->quantitySold <= 0) {
            throw PostingFailed::because('The number sold needs to be more than zero.');
        }

        $stocks = $this->activeStocks($farmUnit);

        if ($stocks->isEmpty()) {
            throw PostingFailed::because('There is no live stock on record to sell from.');
        }

        $totalOnHand = (float) $stocks->sum('current_quantity');
        $requested = (float) $request->quantitySold;

        if ($requested > $totalOnHand) {
            throw PostingFailed::because('That is more than the farm has on record. Please check the number.');
        }

        return [
            'quantity' => $request->quantitySold,
            'allocations' => $this->splitProportionally($stocks, $requested, $totalOnHand),
        ];
    }

    private function activeStocks(?FarmUnit $farmUnit)
    {
        return FarmUnitStock::query()
            ->where('farm_unit_id', $farmUnit?->id)
            ->whereNull('ended_on')
            ->orderBy('started_on')
            ->orderBy('id')
            ->get();
    }

    /** @param \Illuminate\Support\Collection<int, FarmUnitStock> $stocks */
    private function splitProportionally($stocks, float $requested, float $totalOnHand): array
    {
        $allocations = [];
        $allocatedSoFar = 0.0;

        foreach ($stocks as $index => $stock) {
            $isLast = $index === $stocks->count() - 1;
            $available = (float) $stock->current_quantity;

            $share = $isLast
                ? round($requested - $allocatedSoFar, 2)
                : round($requested * ($available / $totalOnHand), 2);

            if ($share > 0) {
                $allocations[] = ['stock' => $stock, 'quantity' => $share];
                $allocatedSoFar += $share;
            }
        }

        return $allocations;
    }

    private function alreadyPosted(PostingRequest $request): ?Transaction
    {
        if ($request->idempotencyKey === null) {
            return null;
        }

        return Transaction::query()
            ->where('idempotency_key', $request->idempotencyKey)
            ->first();
    }

    private function resolveTemplate(PostingRequest $request): TransactionTemplate
    {
        $template = TransactionTemplate::query()->find($request->transactionTemplateId);

        if ($template === null) {
            throw PostingFailed::because('We could not find that kind of record.');
        }

        if (! $template->is_active) {
            throw PostingFailed::because('That kind of record is no longer in use.');
        }

        return $template;
    }

    private function resolveAmount(PostingRequest $request): int
    {
        try {
            $amountMinor = Money::toMinor($request->amount);
        } catch (InvalidArgumentException) {
            throw PostingFailed::because('We could not read that amount. Please enter it in cedis.');
        }

        if ($amountMinor <= 0) {
            throw PostingFailed::because('The amount needs to be more than zero.');
        }

        return $amountMinor;
    }

    private function resolvePeriod(PostingRequest $request): AccountingPeriod
    {
        $date = Carbon::parse($request->transactionDate)->startOfDay();

        if ($date->isAfter(Carbon::today())) {
            throw PostingFailed::because('That date has not happened yet.');
        }

        $period = AccountingPeriod::covering($date->toDateString());

        if ($period === null) {
            throw PostingFailed::because('There is no accounting period covering that date.');
        }

        if (! $period->isOpen()) {
            throw PostingFailed::because('That period is closed. Record it against today instead.');
        }

        return $period;
    }

    private function resolveFarmUnit(PostingRequest $request, TransactionTemplate $template): ?FarmUnit
    {
        if (! $template->requires_farm_unit) {
            return null;
        }

        if ($request->farmUnitId === null) {
            throw PostingFailed::because('Please choose which part of the farm this belongs to.');
        }

        $unit = FarmUnit::query()->find($request->farmUnitId);

        if ($unit === null || (int) $unit->farmer_profile_id !== $request->farmerProfileId) {
            throw PostingFailed::because('We could not find that part of the farm.');
        }

        return $unit;
    }

    private function resolveSettlementAccount(PostingRequest $request, TransactionTemplate $template): ?int
    {
        if ($template->settlement_side === 'none') {
            return null;
        }

        if ($request->settlementAccountId === null) {
            throw PostingFailed::because('Please say where the money went.');
        }

        return $request->settlementAccountId;
    }

    private function write(
        PostingRequest $request,
        TransactionTemplate $template,
        AccountingPeriod $period,
        ?FarmUnit $farmUnit,
        ?int $settlementAccountId,
        int $amountMinor,
        ?array $loss = null,
        ?array $sale = null,
    ): Transaction {
        [$debitAccountId, $creditAccountId] = $this->legs($template, $settlementAccountId);

        return DB::transaction(function () use (
            $request,
            $template,
            $period,
            $farmUnit,
            $settlementAccountId,
            $amountMinor,
            $debitAccountId,
            $creditAccountId,
            $loss,
            $sale
        ) {
            $transaction = $this->writeTransaction(
                $request,
                $template,
                $period,
                $farmUnit,
                $settlementAccountId,
                $amountMinor,
                $loss,
                $sale
            );

            $entry = JournalEntry::create([
                'transaction_id' => $transaction->id,
                'narration' => $request->narration,
                'posted_at' => $transaction->posted_at,
            ]);

            $carried = [
                'journal_entry_id' => $entry->id,
                'farmer_profile_id' => $transaction->farmer_profile_id,
                'transaction_date' => $transaction->transaction_date,
            ];

            JournalLine::create($carried + [
                'ledger_account_id' => $debitAccountId,
                'debit_minor' => $amountMinor,
                'credit_minor' => 0,
                'line_number' => 1,
            ]);

            JournalLine::create($carried + [
                'ledger_account_id' => $creditAccountId,
                'debit_minor' => 0,
                'credit_minor' => $amountMinor,
                'line_number' => 2,
            ]);

            $entry->assertBalanced();

            $this->writeStockMovements($loss, MovementReason::Loss, $transaction, $request);
            $this->writeStockMovements($sale, MovementReason::Sale, $transaction, $request);

            return $transaction;
        });
    }

    private function writeStockMovements(?array $resolved, MovementReason $reason, Transaction $transaction, PostingRequest $request): void
    {
        if ($resolved === null) {
            return;
        }

        foreach ($resolved['allocations'] as $allocation) {
            FarmUnitStockMovement::create([
                'farm_unit_stock_id' => $allocation['stock']->id,
                'reason' => $reason,
                'quantity' => $allocation['quantity'],
                'occurred_on' => $transaction->transaction_date,
                'recorded_by' => $request->recordedBy,
            ]);
        }
    }

    private function legs(TransactionTemplate $template, ?int $settlementAccountId): array
    {
        $debit = (int) $template->debit_account_id;
        $credit = (int) $template->credit_account_id;

        if ($settlementAccountId !== null && $template->settlement_side === 'debit') {
            $debit = $settlementAccountId;
        }

        if ($settlementAccountId !== null && $template->settlement_side === 'credit') {
            $credit = $settlementAccountId;
        }

        if ($debit === $credit) {
            throw PostingFailed::because('Money cannot move from an account into itself.');
        }

        return [$debit, $credit];
    }

    private function writeTransaction(
        PostingRequest $request,
        TransactionTemplate $template,
        AccountingPeriod $period,
        ?FarmUnit $farmUnit,
        ?int $settlementAccountId,
        int $amountMinor,
        ?array $loss = null,
        ?array $sale = null,
    ): Transaction {
        $payload = [
            'farmer_profile_id' => $request->farmerProfileId,
            'transaction_template_id' => $template->id,
            'transaction_type' => $template->transaction_type,
            'accounting_period_id' => $period->id,
            'transaction_date' => $request->transactionDate,
            'amount_minor' => $amountMinor,
            'quantity_lost' => $loss['quantity'] ?? null,
            'quantity_sold' => $sale['quantity'] ?? null,
            'settlement_account_id' => $settlementAccountId,
            'farm_unit_id' => $farmUnit?->id,
            'narration' => $request->narration,
            'channel' => $request->channel,
            'is_provisional' => $farmUnit !== null && $farmUnit->approved_at === null,
            'recorded_by' => $request->recordedBy,
            'idempotency_key' => $request->idempotencyKey,
            'posted_at' => now(),
        ];

        for ($attempt = 1; $attempt <= self::REFERENCE_ATTEMPTS; $attempt++) {
            try {
                return Transaction::create($payload);
            } catch (UniqueConstraintViolationException $collision) {
                if (! str_contains($collision->getMessage(), 'reference')) {
                    throw $collision;
                }
            } catch (Throwable $failure) {
                throw PostingFailed::because($failure->getMessage());
            }
        }

        throw PostingFailed::because('We could not save that record. Please try again.');
    }
}
