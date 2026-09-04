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
    // how many times a clashing reference is redrawn before giving up
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

        return $this->write($request, $template, $period, $farmUnit, $settlementAccountId, $amountMinor, $loss);
    }

    // only a LOSS record ever carries a quantity; the oldest batch absorbs it first,
    // spilling into the next one if it runs out, so the farmer is never asked which batch
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

        $stocks = FarmUnitStock::query()
            ->where('farm_unit_id', $farmUnit?->id)
            ->whereNull('ended_on')
            ->orderBy('started_on')
            ->orderBy('id')
            ->get();

        if ($stocks->isEmpty()) {
            throw PostingFailed::because('There is no live stock on record to take this loss from.');
        }

        $remaining = (float) $request->quantityLost;
        $allocations = [];

        foreach ($stocks as $stock) {
            if ($remaining <= 0) {
                break;
            }

            $available = (float) $stock->current_quantity;

            if ($available <= 0) {
                continue;
            }

            $take = min($available, $remaining);
            $allocations[] = ['stock' => $stock, 'quantity' => $take];
            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw PostingFailed::because('That is more than the farm has on record. Please check the number.');
        }

        return ['quantity' => $request->quantityLost, 'allocations' => $allocations];
    }

    // the phone is not making a mistake, it is retrying after a bad network
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

        // a farmer cannot know today about something that happens next week
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

        // one farmer's pen cannot appear in another farmer's books
        if ($unit === null || (int) $unit->farmer_profile_id !== $request->farmerProfileId) {
            throw PostingFailed::because('We could not find that part of the farm.');
        }

        return $unit;
    }

    // the farmer says where the money sat, and it replaces the leg the template names
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
            $loss
        ) {
            $transaction = $this->writeTransaction(
                $request,
                $template,
                $period,
                $farmUnit,
                $settlementAccountId,
                $amountMinor,
                $loss
            );

            $entry = JournalEntry::create([
                'transaction_id' => $transaction->id,
                'narration' => $request->narration,
                'posted_at' => $transaction->posted_at,
            ]);

            // the farmer and the date ride along, so no report has to join back
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

            // the last gate before the books are committed
            $entry->assertBalanced();

            if ($loss !== null) {
                foreach ($loss['allocations'] as $allocation) {
                    FarmUnitStockMovement::create([
                        'farm_unit_stock_id' => $allocation['stock']->id,
                        'reason' => MovementReason::Loss,
                        'quantity' => $allocation['quantity'],
                        'occurred_on' => $transaction->transaction_date,
                        'recorded_by' => $request->recordedBy,
                    ]);
                }
            }

            return $transaction;
        });
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
    ): Transaction {
        $payload = [
            'farmer_profile_id' => $request->farmerProfileId,
            'transaction_template_id' => $template->id,
            // copied now, so editing the template tomorrow cannot rewrite today
            'transaction_type' => $template->transaction_type,
            'accounting_period_id' => $period->id,
            'transaction_date' => $request->transactionDate,
            'amount_minor' => $amountMinor,
            'quantity_lost' => $loss['quantity'] ?? null,
            'settlement_account_id' => $settlementAccountId,
            'farm_unit_id' => $farmUnit?->id,
            'narration' => $request->narration,
            'channel' => $request->channel,
            // read once, from the unit as it stands right now, and never revisited
            'is_provisional' => $farmUnit !== null && $farmUnit->approved_at === null,
            'recorded_by' => $request->recordedBy,
            'idempotency_key' => $request->idempotencyKey,
            'posted_at' => now(),
        ];

        for ($attempt = 1; $attempt <= self::REFERENCE_ATTEMPTS; $attempt++) {
            try {
                return Transaction::create($payload);
            } catch (UniqueConstraintViolationException $collision) {
                // only a clashing reference is worth redrawing, anything else is a real failure
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
