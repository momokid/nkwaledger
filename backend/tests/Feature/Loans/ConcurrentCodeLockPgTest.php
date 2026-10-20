<?php

// Needs a real PostgreSQL database whose name ends in _test (it is wiped afterwards).
// The agent sending a new code and another request typing the old one, at the very same moment, must never
// wait for each other: both answer normally, none with a database deadlock.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

function startCodeLockChild(string $mode, int $userId, string $entryUuid, string $code, float $startAt): array
{
    $command = [PHP_BINARY, base_path('tests/Support/concurrent-accept.php'), $mode, (string) $userId, $entryUuid, $code, (string) $startAt];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), array_merge(getenv(), ['APP_ENV' => 'testing']));

    return [$process, $pipes];
}

function finishCodeLockChild(array $child): array
{
    [$process, $pipes] = $child;
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    proc_close($process);

    return json_decode($out, true) ?? ['status' => 0, 'body' => $out . $err];
}

test('sending a new code and typing the old one at the same moment never deadlock', function () {
    if (DB::getDriverName() !== 'pgsql' || ! str_ends_with((string) DB::connection()->getDatabaseName(), '_test')) {
        $this->markTestSkipped('needs a PostgreSQL database whose name ends in _test');
    }

    buildLoanWorld($this);
    // end the shared per-test transaction so what was written is visible to the child processes
    DB::commit();

    try {
        $failed = [];

        for ($round = 1; $round <= 30; $round++) {
            $entry = ($this->pendingMoney)(['lender_name' => "Round {$round}"]);
            $this->codes->send($entry, $this->agent);
            $code = ($this->lastCode)();
            $startAt = microtime(true) + 4;

            $answers = array_map('finishCodeLockChild', [
                startCodeLockChild('send', $this->agent->id, $entry->uuid, $code, $startAt),
                startCodeLockChild('code', $this->agent->id, $entry->uuid, $code, $startAt),
            ]);

            if (array_column($answers, 'status') !== [302, 302]) {
                $failed[$round] = $answers;
            }
        }

        expect($failed)->toBe([]);
    } finally {
        Artisan::call('migrate:fresh', ['--force' => true]);
    }
})->group('sequential-pg');
