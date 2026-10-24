<?php

namespace App\Services\Loans;

use App\Models\Loan;
use App\Models\PendingEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

// what the admin sees under Credit: every farmer's loans in one list, and the entries still waiting for a farmer.
// The agent sees the same list, kept to the farmers assigned to them.
// Read-only, newest first, and only public ids leave it
class AdminLoanListService
{
    public const PER_PAGE = 25;
    public const STATUSES = ['all', 'active', 'repaid', 'cancelled', 'waiting'];

    /** the principal and interest still owed on every loan in force */
    public function totalOwed(?int $agentId = null): int
    {
        return (int) Loan::balancesOf($this->ownedBy(Loan::where('status', Loan::ACTIVE), $agentId)->get())->sum();
    }

    /** @return array{rows: list<array<string, mixed>>, pagination: array{page: int, last: int}} */
    public function page(string $search, string $status, int $page, ?int $agentId = null): array
    {
        return $status === 'waiting' ? $this->waiting($search, $page, $agentId) : $this->loans($search, $status, $page, $agentId);
    }

    private function loans(string $search, string $status, int $page, ?int $agentId): array
    {
        $query = $this->ownedBy(Loan::query()->with(['farmerProfile.user', 'lender'])->orderByDesc('created_at')->orderByDesc('id'), $agentId);
        $this->matching($query, $search, true);

        // whether a loan in force is repaid depends on what the books hold, so those two filters are worked out on the list
        if ($status === 'active' || $status === 'repaid') {
            $loans = $query->where('status', Loan::ACTIVE)->get();
            $balances = Loan::balancesOf($loans);
            $kept = $loans->filter(fn(Loan $loan) => ($balances[$loan->id] === 0) === ($status === 'repaid'))->values();

            return [
                'rows' => $kept->slice(($page - 1) * self::PER_PAGE, self::PER_PAGE)->map(fn(Loan $loan) => $this->loanRow($loan, $balances[$loan->id]))->values()->all(),
                'pagination' => ['page' => $page, 'last' => max(1, (int) ceil($kept->count() / self::PER_PAGE))],
            ];
        }

        if ($status === 'cancelled') {
            $query->where('status', Loan::CANCELLED);
        }

        $paged = $query->paginate(self::PER_PAGE, ['*'], 'page', $page);
        $balances = Loan::balancesOf($paged->getCollection());

        return [
            'rows' => $paged->getCollection()->map(fn(Loan $loan) => $this->loanRow($loan, $balances[$loan->id]))->values()->all(),
            'pagination' => $this->pagination($paged),
        ];
    }

    private function waiting(string $search, int $page, ?int $agentId): array
    {
        $query = $this->ownedBy(PendingEntry::query()->with('farmerProfile.user')->where('status', PendingEntry::PENDING)->orderByDesc('id'), $agentId);
        $this->matching($query, $search, false);

        $paged = $query->paginate(self::PER_PAGE, ['*'], 'page', $page);

        return [
            'rows' => $paged->getCollection()->map(fn(PendingEntry $entry) => [
                'row' => 'entry',
                'uuid' => $entry->uuid,
                'kind' => $entry->isRepayment() ? 'repayment' : 'loan',
                'farmer' => $this->farmerName($entry->farmerProfile),
                'lender' => $entry->lenderName(),
                'amount' => $entry->payload['principal_minor'] ?? $entry->payload['amount_minor'],
                'balance' => null,
                'status' => 'pending',
            ])->values()->all(),
            'pagination' => $this->pagination($paged),
        ];
    }

    private function loanRow(Loan $loan, int $balance): array
    {
        return [
            'row' => 'loan',
            'uuid' => $loan->uuid,
            'farmer' => $this->farmerName($loan->farmerProfile),
            'lender' => $loan->lender_name,
            'amount' => $loan->principal_minor,
            'balance' => $balance,
            'status' => Loan::statusFor($loan->status, $balance, $loan->due_date, today()),
        ];
    }

    private function ownedBy(Builder $query, ?int $agentId): Builder
    {
        return $agentId === null ? $query : $query->whereHas('farmerProfile', fn(Builder $farmer) => $farmer->where('assigned_agent_id', $agentId));
    }

    public function farmerName(?\App\Models\FarmerProfile $farmer): string
    {
        return trim("{$farmer?->user?->surname} {$farmer?->user?->first_name}");
    }

    /** every word typed must be found in the farmer's name or, for a loan, in the lender's name */
    private function matching(Builder $query, string $search, bool $byLender): void
    {
        foreach (preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $like = '%' . addcslashes(mb_strtolower($word), '\\%_') . '%';

            $query->where(function (Builder $query) use ($like, $byLender) {
                $query->whereHas('farmerProfile.user', fn(Builder $user) => $user->where(
                    fn(Builder $name) => $name->whereRaw("LOWER(first_name) LIKE ? ESCAPE '\\'", [$like])->orWhereRaw("LOWER(surname) LIKE ? ESCAPE '\\'", [$like])
                ));

                if ($byLender) {
                    $query->orWhereHas('lender', fn(Builder $lender) => $lender->whereRaw("LOWER(name) LIKE ? ESCAPE '\\'", [$like]));
                }
            });
        }
    }

    private function pagination(LengthAwarePaginator $paged): array
    {
        return ['page' => $paged->currentPage(), 'last' => max(1, $paged->lastPage())];
    }
}