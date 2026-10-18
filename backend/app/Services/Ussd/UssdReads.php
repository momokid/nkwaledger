<?php

namespace App\Services\Ussd;

use App\Models\FarmerProfile;
use App\Models\Loan;
use App\Services\Ledger\Reports\IncomeAndExpenditureService;
use App\Services\Loans\LoanService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

// the read-only screens: a summary for a period and the farmer's loans. Nothing here writes
class UssdReads
{
    private const LOANS_SHOWN = 3;
    private const LENDER_WIDTH = 16;

    public function __construct(
        private readonly IncomeAndExpenditureService $incomes,
        private readonly LoanService $loans,
        private readonly UssdText $text,
    ) {}

    public function isPeriod(string $choice): bool
    {
        return in_array($choice, ['1', '2', '3'], true);
    }

    public function summary(FarmerProfile $farmer, string $choice): string
    {
        $to = Carbon::today();
        $from = match ($choice) {
            '2' => $to->copy()->startOfMonth(),
            '3' => $to->copy()->startOfYear(),
            default => $to->copy()->subDays(29),
        };

        // the same figures the web dashboard shows: every record on the farmer's own books
        $report = $this->incomes->for($farmer->id, $from->toDateString(), $to->toDateString(), includeProvisional: true);

        return $this->text->get('summary', [
            'period' => $this->text->get('period_' . $choice),
            'income' => Money::format($report->totalIncomeMinor),
            'expense' => Money::format($report->totalExpenseMinor),
            'net' => Money::format($report->netMinor),
        ]);
    }

    public function loans(FarmerProfile $farmer): string
    {
        $owing = Loan::where('farmer_profile_id', $farmer->id)->where('status', Loan::ACTIVE)->orderByDesc('id')->get();
        $balances = Loan::balancesOf($owing);

        $lines = $owing->filter(fn(Loan $loan) => $balances[$loan->id] > 0)->take(self::LOANS_SHOWN)->map(fn(Loan $loan) => $this->text->get('loan_line', [
            'lender' => Str::limit($loan->lender_name, self::LENDER_WIDTH, ''),
            'balance' => Money::format($balances[$loan->id]),
            'status' => match (Loan::statusFor($loan->status, $balances[$loan->id], $loan->due_date, today())) {
                'overdue' => $this->text->get('loan_overdue'),
                'default' => $this->text->get('loan_default'),
                default => '',
            },
        ]))->implode('');

        return $this->text->get('loans', ['total' => Money::format($this->loans->totalOwedMinor($farmer)), 'lines' => $lines]);
    }
}
