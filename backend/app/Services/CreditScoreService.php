<?php

namespace App\Services;

use App\Models\FarmerProfile;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\ProduceSale;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

// A score out of 100 worked out from the farmer's own records whenever it is shown; nothing is stored. Four pillars
// (config/credit_score.php): farm purchases, money in and out, credit and loans, loan use. A pillar with nothing to
// measure is left out and the others scale up to 100, so having no loans never costs a farmer points.
// A guide only: a loan is never decided by it, and no lender sees it (see the task file).
class CreditScoreService
{
    public const NOTE = 'This score is a guide only. It does not decide a loan.';

    private const NAMES = [
        'purchases' => 'Farm purchases',
        'cash' => 'Money in and out',
        'credit' => 'Credit and loans',
        'loan_use' => 'Loan money used on the farm',
    ];

    public function __construct(private readonly FarmerEligibilityService $eligibility) {}

    /**
     * @return array{status: string, score: ?int, band: ?string, months_with_records: int, pillars: list<array<string, mixed>>}
     */
    public function for(FarmerProfile $farmer, ?CarbonInterface $today = null): array
    {
        $today ??= today();
        $none = fn(string $status, int $months = 0) => ['status' => $status, 'score' => null, 'band' => null, 'months_with_records' => $months, 'pillars' => []];

        if ($farmer->user === null || ! $this->eligibility->canBeCreditScored($farmer->user)) {
            return $none('unavailable');
        }

        $from = $today->copy()->startOfMonth()->subMonths((int) config('credit_score.window_months') - 1);
        $rows = $this->records($farmer, $from, $today);
        $months = $rows->map(fn($row) => $row->transaction_date->format('Y-m'))->unique()->count();

        if ($months < (int) config('credit_score.min_months')) {
            return $none('not_enough', $months);
        }

        $pillars = $this->scaled([
            $this->purchases($rows),
            $this->cash($rows),
            $this->credit($farmer, $from, $today),
            $this->loanUse($farmer, $today),
        ]);

        $score = (int) round(collect($pillars)->sum('scaled_points'));

        return ['status' => 'ok', 'score' => $score, 'band' => $this->bandFor($score), 'months_with_records' => $months, 'pillars' => $pillars];
    }

    public function bandFor(int $score): string
    {
        $band = null;

        foreach (config('credit_score.bands') as $name => $lowest) {
            if ($score >= $lowest) {
                $band = $name;
            }
        }

        return $band;
    }

    /**
     * What a screen shows, in the approved words. $audience is "farmer" or "staff" (the agent and the admin).
     *
     * @return array{text: string, lines: list<string>, note: ?string}
     */
    public function display(FarmerProfile $farmer, string $audience): array
    {
        $result = $this->for($farmer);

        if ($result['status'] === 'unavailable') {
            $explain = $audience === 'farmer' && $farmer->user !== null && $this->eligibility->canTransact($farmer->user) && $farmer->identity_verified_at === null;

            return ['text' => $explain ? 'Not yet available. Your agent can help you confirm your identity document.' : 'Not yet available', 'lines' => [], 'note' => null];
        }

        if ($result['status'] === 'not_enough') {
            $keep = $audience === 'farmer' ? 'Keep recording: we need' : 'We need';

            return ['text' => "Not enough records yet. {$keep} records in at least " . config('credit_score.min_months') . ' of the last ' . config('credit_score.window_months') . ' months.', 'lines' => [], 'note' => null];
        }

        return [
            'text' => "{$result['score']} out of 100 ({$result['band']})",
            'lines' => array_map(fn(array $pillar) => $this->line($pillar), $result['pillars']),
            'note' => self::NOTE,
        ];
    }

    // --- the records that count: live, not held back, and not a produce sale nobody has vouched for ---

    /** @return Collection<int, Transaction> */
    private function records(FarmerProfile $farmer, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $held = ProduceSale::query()
            ->where('farmer_profile_id', $farmer->id)
            ->whereNotNull('ledger_transaction_id')
            ->get()
            ->reject(fn(ProduceSale $sale) => $sale->countsTowardCredit())
            ->pluck('ledger_transaction_id')
            ->all();

        return Transaction::query()
            ->live()
            ->where('farmer_profile_id', $farmer->id)
            ->where('is_provisional', false)
            ->whereIn('transaction_type', Transaction::FIGURE_TYPES)
            ->whereDate('transaction_date', '>=', $from->toDateString())
            ->whereDate('transaction_date', '<=', $to->toDateString())
            ->when($held !== [], fn($query) => $query->whereNotIn('id', $held))
            ->get(['id', 'transaction_date', 'transaction_type']);
    }

    private function monthsOf(Collection $rows, string $type): Collection
    {
        return $rows->where('transaction_type', $type)->map(fn($row) => $row->transaction_date->format('Y-m'))->unique()->values();
    }

    // --- the four pillars: each returns its raw points out of its own weight ---

    private function purchases(Collection $rows): array
    {
        $months = $this->monthsOf($rows, Transaction::EXPENSE)->count();

        return $this->pillar('purchases', true, $months / (int) config('credit_score.window_months'), ['months' => $months]);
    }

    private function cash(Collection $rows): array
    {
        $months = $this->monthsOf($rows, Transaction::INCOME)->intersect($this->monthsOf($rows, Transaction::EXPENSE))->count();

        return $this->pillar('cash', true, $months / (int) config('credit_score.window_months'), ['months' => $months]);
    }

    // payments early or on time against the due date; a loan past its due date lowers the whole pillar
    private function credit(FarmerProfile $farmer, CarbonInterface $from, CarbonInterface $today): array
    {
        $paid = $onTime = $overdue = 0;
        $default = false;

        Loan::query()->where('farmer_profile_id', $farmer->id)->where('kind', Loan::KIND_MONEY)->where('status', Loan::ACTIVE)->get()
            ->each(function (Loan $loan) use ($from, $today, &$paid, &$onTime, &$overdue, &$default) {
                $status = Loan::statusFor($loan->status, $loan->balanceMinor(), $loan->due_date, $today);

                if (in_array($status, ['overdue', 'default'], true)) {
                    $overdue++;
                    $default = $default || $status === 'default';
                }

                if ($loan->due_date === null) {
                    return;
                }

                LoanRepayment::where('loan_id', $loan->id)->whereDate('repaid_on', '>=', $from->toDateString())->whereDate('repaid_on', '<=', $today->toDateString())->get()
                    ->filter(fn(LoanRepayment $payment) => Transaction::whereIn('id', array_filter([$payment->principal_transaction_id, $payment->interest_transaction_id]))->notCancelled()->exists())
                    ->each(function (LoanRepayment $payment) use ($loan, &$paid, &$onTime) {
                        $paid++;
                        $onTime += $payment->repaid_on->lte($loan->due_date) ? 1 : 0;
                    });
            });

        $factor = $default ? 0.0 : ($overdue > 0 ? (float) config('credit_score.overdue_factor') : 1.0);
        $ratio = $paid > 0 ? $onTime / $paid : 0.0;

        return $this->pillar('credit', $paid > 0 || $overdue > 0, $ratio * $factor, ['paid' => $paid, 'on_time' => $onTime, 'overdue' => $overdue]);
    }

    // how much of the loan money named for farms has gone into farm records; loans younger than a week are not judged yet
    private function loanUse(FarmerProfile $farmer, CarbonInterface $today): array
    {
        $planned = $accounted = 0;
        $cutoff = $today->copy()->subDays((int) config('credit_score.loan_use_min_age_days'))->endOfDay();

        Loan::query()->where('farmer_profile_id', $farmer->id)->where('kind', Loan::KIND_MONEY)->where('status', Loan::ACTIVE)
            ->where('created_at', '<=', $cutoff)->whereHas('farmAllocations')->get()
            ->each(function (Loan $loan) use (&$planned, &$accounted) {
                foreach ($loan->farmFigures() as $farm) {
                    $planned += $farm['planned'];
                    $accounted += min($farm['accounted'], $farm['planned']);
                }
            });

        $share = $planned > 0 ? $accounted / $planned : 0.0;

        return $this->pillar('loan_use', $planned > 0, $share, ['percent' => (int) round($share * 100)]);
    }

    // a pillar's raw points are its weight times a share between 0 and 1
    private function pillar(string $key, bool $counted, float $share, array $detail): array
    {
        return ['key' => $key, 'name' => self::NAMES[$key], 'counted' => $counted, 'weight' => (int) config("credit_score.weights.{$key}"), 'raw_points' => (float) ($counted ? config("credit_score.weights.{$key}") * $share : 0)] + $detail;
    }

    // the pillars that count are scaled so their weights add up to 100
    private function scaled(array $pillars): array
    {
        $total = collect($pillars)->where('counted', true)->sum('weight');

        $factors = array_map(fn(array $pillar) => $pillar['counted'] && $total > 0 ? 100 / $total : 0, $pillars);
        $scaled = array_map(fn(array $pillar, $factor) => $pillar['raw_points'] * $factor, $pillars, $factors);
        $maxes = $this->roundToSum(array_map(fn(array $pillar, $factor) => $pillar['weight'] * $factor, $pillars, $factors), $total > 0 ? 100 : 0);
        $points = $this->roundToSum($scaled, (int) round(array_sum($scaled)));

        return array_map(fn(array $pillar, $scaledPoints, $max, $point) => $pillar + ['scaled_points' => $scaledPoints, 'max' => $max, 'points' => $point], $pillars, $scaled, $maxes, $points);
    }

    // whole numbers that keep the order of the input and add up to $target, so the lines on a screen always match the total
    private function roundToSum(array $values, int $target): array
    {
        $whole = array_map(fn($v) => (int) floor($v), $values);
        $left = $target - array_sum($whole);
        $byRemainder = array_keys($values);
        usort($byRemainder, fn($a, $b) => ($values[$b] - $whole[$b]) <=> ($values[$a] - $whole[$a]));

        foreach (array_slice($byRemainder, 0, max($left, 0)) as $i) {
            $whole[$i]++;
        }

        return $whole;
    }

    private function line(array $pillar): string
    {
        $name = $pillar['name'];

        if (! $pillar['counted']) {
            return "{$name}: not counted yet.";
        }

        $head = "{$name}: {$pillar['points']} out of {$pillar['max']}.";
        $window = config('credit_score.window_months');

        return match ($pillar['key']) {
            'purchases' => "{$head} Recorded in {$pillar['months']} of the last {$window} months.",
            'cash' => "{$head} Both recorded in {$pillar['months']} of the last {$window} months.",
            'credit' => trim($head
                . ($pillar['paid'] > 0 ? " {$pillar['on_time']} of {$pillar['paid']} payments were early or on time." : '')
                . ($pillar['overdue'] > 0 ? " {$pillar['overdue']} loan(s) overdue." : '')),
            'loan_use' => "{$head} {$pillar['percent']}% accounted for.",
        };
    }
}
