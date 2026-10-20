<?php

namespace App\Services\Loans;

use App\Models\Loan;
use App\Models\LoanCheck;
use App\Support\Wording;
use Illuminate\Support\Collection;

// the admin's Loan Analysis: the loans in force and repaid, added up by a chosen grouping. A cancelled loan is left out.
// In every row: amount + interest = repaid + balance. A loan counts under each label it has (a farmer with two farm
// types is under both), but the totals count every loan once
class LoanAnalysisService
{
    public const GROUPS = ['region', 'district', 'farm_type', 'lender_type', 'lender', 'farmer_group', 'agent', 'month', 'status', 'checked'];

    // shown where a loan has no region, group, agent, farm type or check
    public const NONE = '-';

    /** @return array{rows: list<array<string, mixed>>, totals: array<string, int>} */
    public function analyse(string $group, ?string $from, ?string $to): array
    {
        // ponytail: every loan in range is read into memory; page or aggregate in SQL if loans reach the tens of thousands
        $loans = Loan::query()
            ->where('status', Loan::ACTIVE)
            ->when($from, fn($query) => $query->whereDate('loan_date', '>=', $from))
            ->when($to, fn($query) => $query->whereDate('loan_date', '<=', $to))
            ->with(['lender', 'currentCheck', 'farmerProfile.assignedAgent', 'farmerProfile.farmerGroup', 'farmerProfile.farmTypes', 'farmerProfile.community.district.region'])
            ->get();

        $balances = Loan::balancesOf($loans);
        $buckets = [];
        $all = [];

        foreach ($loans as $loan) {
            $figures = $this->figuresOf($loan, $balances[$loan->id]);
            $all[] = $figures;

            foreach ($this->labelsFor($group, $loan, $balances[$loan->id]) as $label) {
                $buckets[$label][] = $figures;
            }
        }

        uksort($buckets, fn($a, $b) => $this->order($group, $a, $b));

        return [
            'rows' => collect($buckets)->map(fn(array $figures, string $label) => ['label' => $label] + $this->sum($figures))->values()->all(),
            'totals' => $this->sum($all),
        ];
    }

    private function figuresOf(Loan $loan, int $balance): array
    {
        $amount = $loan->principal_minor;
        $interest = (int) $loan->agreed_interest_minor;

        return ['amount' => $amount, 'interest' => $interest, 'balance' => $balance, 'repaid' => $amount + $interest - $balance];
    }

    /** @param list<array<string, int>> $figures */
    private function sum(array $figures): array
    {
        $amount = array_sum(array_column($figures, 'amount'));
        $interest = array_sum(array_column($figures, 'interest'));
        $repaid = array_sum(array_column($figures, 'repaid'));

        return [
            'loans' => count($figures),
            'amount' => $amount,
            'interest' => $interest,
            'repaid' => $repaid,
            'balance' => array_sum(array_column($figures, 'balance')),
            'repaid_percent' => $amount + $interest > 0 ? (int) round($repaid / ($amount + $interest) * 100) : 0,
        ];
    }

    /** @return list<string> */
    private function labelsFor(string $group, Loan $loan, int $balance): array
    {
        $farmer = $loan->farmerProfile;
        $check = $loan->currentCheck;

        $labels = match ($group) {
            'region' => [$farmer?->community?->district?->region?->name],
            'district' => [$farmer?->community?->district?->name],
            'farm_type' => $farmer?->farmTypes->pluck('name')->all() ?? [],
            'lender_type' => [$loan->lender === null ? null : Wording::get("lender.type.{$loan->lender->type}")],
            'lender' => [$loan->lender_name],
            'farmer_group' => [$farmer?->farmerGroup?->name],
            'agent' => [$farmer?->assignedAgent === null ? null : trim("{$farmer->assignedAgent->surname} {$farmer->assignedAgent->first_name}")],
            'month' => [$loan->loan_date->format('Y-m')],
            'status' => [$balance === 0 ? 'Repaid' : 'Active'],
            'checked' => [$check === null || $check->status === LoanCheck::WITHDRAWN ? null : Wording::get("lender.check_option_{$check->status}")],
        };

        $labels = array_values(array_unique(array_filter($labels, fn($label) => $label !== null && $label !== '')));

        return $labels === [] ? [self::NONE] : $labels;
    }

    // alphabetical, newest month first, and the dash last
    private function order(string $group, string $a, string $b): int
    {
        if ($a === self::NONE || $b === self::NONE) {
            return ($a === self::NONE) <=> ($b === self::NONE);
        }

        return $group === 'month' ? strcmp($b, $a) : strnatcasecmp($a, $b);
    }
}