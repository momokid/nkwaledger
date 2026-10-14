<?php

// Needs a real PostgreSQL database whose name ends in _test (it is wiped afterwards).
// Two admins adding "Societe" and "Société" at the very same moment: exactly one lender is made.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\Lender;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

function startAddChild(int $userId, string $name, float $startAt): array
{
    $command = [PHP_BINARY, base_path('tests/Support/concurrent-add-lender.php'), (string) $userId, $name, (string) $startAt];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), array_merge(getenv(), ['APP_ENV' => 'testing']));

    return [$process, $pipes];
}

function finishAddChild(array $child): array
{
    [$process, $pipes] = $child;
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    proc_close($process);

    return childAnswer($out, $err);
}

test('two admins adding Societe and Société at the same moment make exactly one lender', function () {
    if (DB::getDriverName() !== 'pgsql' || ! str_ends_with((string) DB::connection()->getDatabaseName(), '_test')) {
        $this->markTestSkipped('needs a PostgreSQL database whose name ends in _test');
    }

    buildLoanWorld($this);
    // end the shared per-test transaction so what was written is visible to the child processes
    DB::commit();

    try {
        $bad = [];

        for ($round = 1; $round <= 15; $round++) {
            $startAt = microtime(true) + 4;
            $answers = array_map('finishAddChild', [
                startAddChild($this->admin->id, "Societe {$round}", $startAt),
                startAddChild($this->admin->id, "Société {$round}", $startAt),
            ]);

            $statuses = array_column($answers, 'status');
            sort($statuses);

            if ($statuses !== [201, 422] || Lender::where('sort_key', "societe {$round}")->count() !== 1) {
                $bad[$round] = $answers;
            }
        }

        expect($bad)->toBe([]);
    } finally {
        Artisan::call('migrate:fresh', ['--force' => true]);
    }
})->group('sequential-pg');
