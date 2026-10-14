<?php

// Needs a real PostgreSQL database whose name ends in _test (it is wiped afterwards).
// Two repayments on one loan at the very same moment, each fine alone, must not overpay it together.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\LoanRepayment;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

function startRepayChild(int $userId, string $loanUuid, string $amount, string $key, float $startAt): array
{
    $command = [PHP_BINARY, base_path('tests/Support/concurrent-repay.php'), (string) $userId, $loanUuid, $amount, $key, (string) $startAt];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), array_merge(getenv(), ['APP_ENV' => 'testing']));

    return [$process, $pipes];
}

function finishRepayChild(array $child): array
{
    [$process, $pipes] = $child;
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    proc_close($process);

    return childAnswer($out, $err);
}

test('two repayments at the same moment cannot overpay the loan', function () {
    if (DB::getDriverName() !== 'pgsql' || ! str_ends_with((string) DB::connection()->getDatabaseName(), '_test')) {
        $this->markTestSkipped('needs a PostgreSQL database whose name ends in _test');
    }

    buildLoanWorld($this);
    DB::commit();

    try {
        $overpaid = [];

        for ($round = 1; $round <= 10; $round++) {
            // 1000 principal + 200 interest = 1200 owed; each 1000 is fine alone, both together are not
            $loan = ($this->openLoan)(['lender_name' => "Round {$round}"]);
            $startAt = microtime(true) + 4;

            $answers = array_map('finishRepayChild', [
                startRepayChild($this->farmerUser->id, $loan->uuid, '1000', "a-{$round}", $startAt),
                startRepayChild($this->farmerUser->id, $loan->uuid, '1000', "b-{$round}", $startAt),
            ]);

            $paid = (int) LoanRepayment::where('loan_id', $loan->id)->sum('amount_minor');

            if ($paid !== 100000 || LoanRepayment::where('loan_id', $loan->id)->count() !== 1) {
                $overpaid[] = [$round, $paid, $answers];
            }
        }

        expect($overpaid)->toBe([])
            ->and(LoanRepayment::whereNotNull('interest_transaction_id')->count())->toBe(10);
    } finally {
        Artisan::call('migrate:fresh', ['--force' => true]);
    }
})->group('sequential-pg');
