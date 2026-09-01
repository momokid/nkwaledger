<?php

namespace App\Services\Ledger;

use App\Exceptions\Ledger\PostingFailed;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\ReversalRequest;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use App\Services\NotificationService;

class ReversalService
{
    private const REFERENCE_ATTEMPTS = 5;

    public function __construct(private readonly NotificationService $notifications) {}

    public function request(Transaction $transaction, User $requestedBy, string $reason): ReversalRequest
    {
        if (blank($reason)) {
            throw PostingFailed::because('Please say why this record needs to be cancelled.');
        }

        $this->guardCanBeReversed($transaction);

        $request = ReversalRequest::create([
            'transaction_id' => $transaction->id,
            'reason' => $reason,
            'requested_by' => $requestedBy->id,
            'requested_at' => now(),
        ]);

        $this->notifications->sendToPermission(
            permission: 'transactions.reverse-approve',
            kind: 'reversal.requested',
            message: "Somebody wants to cancel record {$transaction->reference}. {$reason}",
            link: '/admin/approvals',
            except: $requestedBy,
        );

        return $request;
    }

    public function approve(ReversalRequest $request, User $approvedBy): Transaction
    {
        $this->guardCanBeSettled($request, $approvedBy);

        $original = $request->transaction;

        $this->guardCanBeReversed($original, $request);

        return DB::transaction(function () use ($request, $original, $approvedBy) {
            $reversal = $this->post($original, $request, $approvedBy);

            $request->forceFill([
                'status' => ReversalRequest::APPROVED,
                'approved_by' => $approvedBy->id,
                'approved_at' => now(),
                'reversal_transaction_id' => $reversal->id,
            ])->save();

            $this->notifications->send(
                user: $request->requestedBy,
                kind: 'reversal.approved',
                message: "Record {$original->reference} is cancelled. A correction is in the book.",
                link: '/my-records',
            );

            return $reversal;
        });
    }

    // a refusal belongs on the trail as much as an agreement does
    public function reject(ReversalRequest $request, User $rejectedBy, string $reason): ReversalRequest
    {
        $this->guardCanBeSettled($request, $rejectedBy);

        $request->forceFill([
            'status' => ReversalRequest::REJECTED,
            'rejected_by' => $rejectedBy->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        $this->notifications->send(
            user: $request->requestedBy,
            kind: 'reversal.rejected',
            message: "Record {$request->transaction?->reference} stays as it is. {$reason}",
            link: '/my-records',
        );

        return $request;
    }

    private function guardCanBeSettled(ReversalRequest $request, User $decidedBy): void
    {
        if (! $request->isPending()) {
            throw PostingFailed::because('That request has already been settled.');
        }

        // the person who asks cannot be the person who agrees
        if ((int) $request->requested_by === $decidedBy->id) {
            throw PostingFailed::because('Somebody else has to agree to this cancellation.');
        }
    }

    private function guardCanBeReversed(Transaction $transaction, ?ReversalRequest $ignore = null): void
    {
        // a correction of a correction hides the trail
        if ($transaction->isAdjustment()) {
            throw PostingFailed::because('A correction cannot itself be cancelled.');
        }

        if ($transaction->reversedBy()->exists()) {
            throw PostingFailed::because('That record has already been cancelled.');
        }

        $pending = ReversalRequest::query()
            ->where('transaction_id', $transaction->id)
            ->where('status', ReversalRequest::PENDING)
            ->when($ignore !== null, fn($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists();

        if ($pending) {
            throw PostingFailed::because('Somebody has already asked to cancel that record.');
        }
    }

    private function post(Transaction $original, ReversalRequest $request, User $approvedBy): Transaction
    {
        $period = AccountingPeriod::covering(now()->toDateString());

        if ($period === null) {
            throw PostingFailed::because(
                'There is no accounting period covering today, so this cancellation cannot be recorded yet.',
            );
        }

        if (! $period->isOpen()) {
            throw PostingFailed::because('Today falls in a closed period, so nothing can be recorded.');
        }

        $template = $this->adjustmentTemplate();

        $reversal = $this->writeTransaction($original, $request, $approvedBy, $period, $template);

        $entry = JournalEntry::create([
            'transaction_id' => $reversal->id,
            'narration' => $reversal->narration,
            'posted_at' => $reversal->posted_at,
        ]);

        $originalEntry = JournalEntry::where('transaction_id', $original->id)->firstOrFail();

        $carried = [
            'journal_entry_id' => $entry->id,
            'farmer_profile_id' => $reversal->farmer_profile_id,
            'transaction_date' => $reversal->transaction_date,
        ];

        // the sides swap, so the money goes back the way it came
        foreach ($originalEntry->lines as $index => $line) {
            JournalLine::create($carried + [
                'ledger_account_id' => $line->ledger_account_id,
                'debit_minor' => $line->credit_minor,
                'credit_minor' => $line->debit_minor,
                'line_number' => $index + 1,
            ]);
        }

        $entry->assertBalanced();

        return $reversal;
    }

    private function writeTransaction(
        Transaction $original,
        ReversalRequest $request,
        User $approvedBy,
        AccountingPeriod $period,
        TransactionTemplate $template,
    ): Transaction {
        $payload = [
            'farmer_profile_id' => $original->farmer_profile_id,
            'transaction_template_id' => $template->id,
            'transaction_type' => Transaction::ADJUSTMENT,
            'accounting_period_id' => $period->id,
            // the fix belongs to today, not to the day the mistake was made
            'transaction_date' => now()->toDateString(),
            'amount_minor' => $original->amount_minor,
            'settlement_account_id' => $original->settlement_account_id,
            'farm_unit_id' => $original->farm_unit_id,
            'narration' => "Cancels {$original->reference}. {$request->reason}",
            'channel' => 'web',
            // the sticker follows the record it puts right
            'is_provisional' => (bool) $original->is_provisional,
            'reverses_transaction_id' => $original->id,
            'recorded_by' => $approvedBy->id,
            'posted_at' => now(),
        ];

        for ($attempt = 1; $attempt <= self::REFERENCE_ATTEMPTS; $attempt++) {
            try {
                return Transaction::create($payload);
            } catch (UniqueConstraintViolationException $collision) {
                if (! str_contains($collision->getMessage(), 'reference')) {
                    throw $collision;
                }
            }
        }

        throw PostingFailed::because('We could not save that cancellation. Please try again.');
    }

    private function adjustmentTemplate(): TransactionTemplate
    {
        $template = TransactionTemplate::query()
            ->where('transaction_type', Transaction::ADJUSTMENT)
            ->where('is_active', true)
            ->first();

        if ($template === null) {
            throw PostingFailed::because('No correction type has been set up yet.');
        }

        return $template;
    }
}
