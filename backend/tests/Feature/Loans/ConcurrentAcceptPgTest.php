<?php

// Needs a real PostgreSQL database whose name ends in _test (it is wiped afterwards).
// The farmer pressing Accept and the agent typing the code, at the very same moment, must make exactly one loan.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\PendingEntry;
use App\Models\Transaction;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

function startAcceptChild(string $mode, int $userId, string $entryUuid, string $code, float $startAt): array
{
    $command = [PHP_BINARY, base_path('tests/Support/concurrent-accept.php'), $mode, (string) $userId, $entryUuid, $code, (string) $startAt];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), array_merge(getenv(), ['APP_ENV' => 'testing']));

    return [$process, $pipes];
}

function finishAcceptChild(array $child): array
{
    [$process, $pipes] = $child;
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    proc_close($process);

    return json_decode($out, true) ?? ['status' => 0, 'body' => $out . $err];
}

test('the farmer pressing Accept and the agent typing the code at the same moment make one loan', function () {
    if (DB::getDriverName() !== 'pgsql' || ! str_ends_with((string) DB::connection()->getDatabaseName(), '_test')) {
        $this->markTestSkipped('needs a PostgreSQL database whose name ends in _test');
    }

    buildLoanWorld($this);
    // end the shared per-test transaction so what was written is visible to the child processes
    DB::commit();

    try {
        $doubled = [];

        for ($round = 1; $round <= 15; $round++) {
            $entry = ($this->pendingMoney)(['lender_name' => "Round {$round}"]);
            $this->codes->send($entry, $this->agent);
            $code = ($this->lastCode)();
            $startAt = microtime(true) + 4;

            $children = [
                startAcceptChild('app', $this->farmerUser->id, $entry->uuid, $code, $startAt),
                startAcceptChild('code', $this->agent->id, $entry->uuid, $code, $startAt),
            ];
            $answers = array_map('finishAcceptChild', $children);

            expect(array_column($answers, 'status'))->each->toBe(302, "round {$round}: " . json_encode($answers));

            $loans = Loan::where('lender_name', "Round {$round}")->count();

            if ($loans !== 1 || PendingEntry::find($entry->id)->status !== 'accepted') {
                $doubled[] = $round;
            }
        }

        expect($doubled)->toBe([])
            ->and(Loan::count())->toBe(15)
            ->and(Transaction::where('transaction_type', 'LOAN')->count())->toBe(15)
            ->and(JournalEntry::whereIn('transaction_id', Transaction::where('transaction_type', 'LOAN')->pluck('id'))->count())->toBe(15);
    } finally {
        Artisan::call('migrate:fresh', ['--force' => true]);
    }
})->group('sequential-pg');
