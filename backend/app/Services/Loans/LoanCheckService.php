<?php

namespace App\Services\Loans;

use App\Exceptions\Ledger\PostingFailed;
use App\Models\Loan;
use App\Models\LoanCheck;
use App\Models\User;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Support\AgentLoanLink;
use App\Support\Wording;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// an admin's check of a loan with its lender. The farmer and the farmer's agent read the result and the note,
// and are told when it is saved
class LoanCheckService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly NotificationService $notifications,
    ) {}

    public function record(Loan $loan, User $by, string $status, ?string $note, ?string $key): LoanCheck
    {
        return DB::transaction(function () use ($loan, $by, $status, $note, $key) {
            // two admins at once meet here, and the second sees what the first wrote
            $locked = Loan::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if ($key !== null && ($twin = LoanCheck::where('loan_id', $locked->id)->where('idempotency_key', $key)->first()) !== null) {
                return $twin;
            }

            if ($locked->isCancelled()) {
                throw PostingFailed::because(Wording::FALLBACK);
            }

            $check = LoanCheck::create([
                'loan_id' => $locked->id,
                'recorded_by' => $by->id,
                'status' => $status,
                'note' => $note,
                'idempotency_key' => $key,
            ]);

            $this->audit->recordOn('loan_check.created', $check, null, [
                'uuid' => $check->uuid,
                'loan_uuid' => $locked->uuid,
                'status' => $status,
                'note' => $note,
            ], $by);

            $this->tell($locked, $status);

            return $check;
        });
    }

    // the current check is taken back: a new row records it, and the loan reads as unchecked again
    public function withdraw(Loan $loan, User $by, string $note, ?string $key): LoanCheck
    {
        return DB::transaction(function () use ($loan, $by, $note, $key) {
            $locked = Loan::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if ($key !== null && ($twin = LoanCheck::where('loan_id', $locked->id)->where('idempotency_key', $key)->first()) !== null) {
                return $twin;
            }

            $current = $locked->currentCheck()->first();

            if ($current === null || $current->status === LoanCheck::WITHDRAWN || $locked->isCancelled()) {
                throw PostingFailed::because(Wording::FALLBACK);
            }

            $check = LoanCheck::create([
                'loan_id' => $locked->id,
                'recorded_by' => $by->id,
                'status' => LoanCheck::WITHDRAWN,
                'note' => $note,
                'idempotency_key' => $key,
            ]);

            $this->audit->recordOn('loan_check.withdrawn', $check, ['status' => $current->status], [
                'uuid' => $check->uuid,
                'loan_uuid' => $locked->uuid,
                'withdraws' => $current->uuid,
                'note' => $note,
            ], $by);

            $this->tell($locked, LoanCheck::WITHDRAWN);

            return $check;
        });
    }

    /** @return Collection<int, array<string, mixed>> every check on the loan, newest first, with who made it */
    public function history(Loan $loan): Collection
    {
        return $loan->checks()->with('recordedBy')->get()->map(fn(LoanCheck $check) => [
            'uuid' => $check->uuid,
            'status' => $check->status,
            'note' => $check->note,
            'date' => $check->created_at->toDateString(),
            'by' => trim("{$check->recordedBy?->surname} {$check->recordedBy?->first_name}"),
        ])->values();
    }

    /** @return array{status: string, note: ?string, date: string}|null what the farmer and the agent are shown: no id, no admin. A withdrawn check shows as none */
    public static function present(?LoanCheck $check): ?array
    {
        return $check === null || $check->status === LoanCheck::WITHDRAWN ? null : [
            'status' => $check->status,
            'note' => $check->note,
            'date' => $check->created_at->toDateString(),
        ];
    }

    private function tell(Loan $loan, string $status): void
    {
        $farmer = $loan->farmerProfile;
        $message = Wording::get("lender.check_notice_{$status}", ['lender' => $loan->lender_name]);

        if ($farmer->user !== null) {
            $this->notifications->send($farmer->user, 'loan.checked', $message, "/loans/{$loan->uuid}");
        }

        if ($farmer->assignedAgent !== null) {
            $this->notifications->send($farmer->assignedAgent, 'loan.checked', $message, AgentLoanLink::to($loan->uuid, $loan->checks()->latest('id')->value('uuid')));
        }
    }
}