<?php

// Needs a real PostgreSQL database whose name ends in _test (it is wiped afterwards).
// An admin cancelling a loan and a waiting repayment on that loan being accepted, at nearly the same moment, must not
// wait for each other: neither request fails, and the loan ends in one coherent state. Odd rounds accept in the app,
// even rounds accept with the SMS code.
//
// Started together, the accept is always done long before the cancel reaches the loan row. So every round measures
// when the cancel took the loan and when the accept took the entry and the loan, and the next round starts the accept
// where those times would meet, plus a small swing either way, so the two requests keep meeting inside each other's
// locks. A pass only counts if enough rounds really did: the proof of overlap is part of the test.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\PendingEntry;
use App\Models\Transaction;
use App\Services\Ledger\ReversalService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// 30 rounds in the suite; set CANCEL_RACE_ROUNDS=100 for a longer run
define('CANCEL_RACE_ROUNDS', (int) (getenv('CANCEL_RACE_ROUNDS') ?: 30));
const CANCEL_RACE_CALIBRATION = 4;
define('CANCEL_RACE_MIN_INTERLEAVED', (int) ceil(CANCEL_RACE_ROUNDS / 6));

function startCancelRaceChild(string $mode, int $userId, string $target, string $code, float $startAt): array
{
    $command = [PHP_BINARY, base_path('tests/Support/concurrent-accept.php'), $mode, (string) $userId, $target, $code, (string) $startAt];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), array_merge(getenv(), ['APP_ENV' => 'testing']));

    return [$process, $pipes];
}

function finishCancelRaceChild(array $child): array
{
    [$process, $pipes] = $child;
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    proc_close($process);

    return json_decode($out, true) ?? ['status' => 0, 'body' => $out . $err];
}

// one request asked for a lock while the other was holding the lock the first one needs next
function cancelRaceInterleaved(array $cancel, array $accept): bool
{
    $cancelAsksLoan = $cancel['events']['loan']['asked'] ?? null;
    $acceptHasEntry = $accept['events']['entry']['got'] ?? null;
    $acceptAsksEntry = $accept['events']['entry']['asked'] ?? null;
    $cancelHasLoan = $cancel['events']['loan']['got'] ?? null;

    return ($cancelAsksLoan !== null && $acceptHasEntry !== null && $cancelAsksLoan >= $acceptHasEntry && $cancelAsksLoan <= $accept['done'])
        || ($acceptAsksEntry !== null && $cancelHasLoan !== null && $acceptAsksEntry >= $cancelHasLoan && $acceptAsksEntry <= $cancel['done']);
}

// how much later the accept should start for its middle lock to land on the cancel's loan lock; null if a lock is missing
function cancelRaceMeetingOffset(array $cancel, array $accept): ?float
{
    $cancelLoan = $cancel['events']['loan']['got'] ?? null;
    $entry = $accept['events']['entry']['got'] ?? null;

    if ($cancelLoan === null || $entry === null) {
        return null;
    }

    $middle = isset($accept['events']['loan']['got']) ? ($entry + $accept['events']['loan']['got']) / 2 : $entry + 0.025;

    return ($cancelLoan - $cancel['wake']) - ($middle - $accept['wake']);
}

test('cancelling a loan and accepting a waiting repayment at the same moment never deadlock', function () {
    if (DB::getDriverName() !== 'pgsql' || ! str_ends_with((string) DB::connection()->getDatabaseName(), '_test')) {
        $this->markTestSkipped('needs a PostgreSQL database whose name ends in _test');
    }

    buildLoanWorld($this);
    // end the shared per-test transaction so what was written is visible to the child processes
    DB::commit();

    try {
        $reversals = app(ReversalService::class);

        // $offsetMs > 0 starts the accept later than the cancel, < 0 earlier
        $race = function (int $round, int $offsetMs) use ($reversals): array {
            $loan = ($this->openLoan)(['lender_name' => "Round {$round}"]);
            $request = $reversals->request(Transaction::find($loan->transaction_id), $this->clerk, 'Typed wrongly');
            $entry = $this->entries->recordRepayment($loan, $this->agent, ($this->repayForm)('600', []));
            $viaCode = $round % 2 === 0;
            $code = '';

            if ($viaCode) {
                $this->codes->send($entry, $this->agent);
                preg_match('/Code: (\d{6})$/', end(app(\App\Contracts\SmsProvider::class)->sent)['message'], $match);
                $code = $match[1];
            }

            $start = microtime(true) + 4;
            [$cancel, $accept] = array_map('finishCancelRaceChild', [
                startCancelRaceChild('cancel', $this->admin->id, $request->uuid, '', $start + max(0, -$offsetMs) / 1000),
                $viaCode
                    ? startCancelRaceChild('code', $this->agent->id, $entry->uuid, $code, $start + max(0, $offsetMs) / 1000)
                    : startCancelRaceChild('app', $this->farmerUser->id, $entry->uuid, '', $start + max(0, $offsetMs) / 1000),
            ]);

            $entryNow = PendingEntry::find($entry->id);
            $repayments = LoanRepayment::where('loan_id', $loan->id)->count();
            $cancelled = Loan::find($loan->id)->status === Loan::CANCELLED;
            $reversalCount = Transaction::where('reverses_transaction_id', $loan->transaction_id)->count();

            $state = match (true) {
                $entryNow->status === 'accepted' && $repayments === 1 && $cancelled && $reversalCount === 1 => 'accepted first',
                $entryNow->status === 'rejected' && $repayments === 0 && $cancelled && $reversalCount === 1 => 'cancelled first',
                default => null,
            };

            return [
                'ok' => $state !== null && $cancel['status'] === 302 && $accept['status'] === 302,
                'state' => $state,
                'detail' => ['answers' => [$cancel, $accept], 'entry' => $entryNow->status, 'repayments' => $repayments, 'cancelled' => $cancelled, 'reversals' => $reversalCount],
                'cancel' => $cancel,
                'accept' => $accept,
            ];
        };

        $wrong = [];

        // the first rounds start together and only measure
        $estimates = [];

        for ($round = 1; $round <= CANCEL_RACE_CALIBRATION; $round++) {
            $result = $race($round, 0);
            $result['ok'] || $wrong["calibration {$round}"] = $result['detail'];
            ($estimate = cancelRaceMeetingOffset($result['cancel'], $result['accept'])) === null || $estimates[] = $estimate;
        }

        expect($estimates)->not->toBe([], 'the calibration rounds did not show both requests taking their locks');

        $interleaved = 0;
        $waited = 0;
        $pids = [];
        $outcomes = ['accepted first' => 0, 'cancelled first' => 0];
        $swing = [-40, -30, -20, -10, 0, 10, 20, 30, 40];
        $lastCentre = 0;

        for ($round = 1; $round <= CANCEL_RACE_ROUNDS; $round++) {
            $recent = array_slice($estimates, -3);
            sort($recent);
            $lastCentre = (int) round($recent[intdiv(count($recent), 2)] * 1000);
            $offset = $lastCentre + $swing[($round - 1) % count($swing)];

            $result = $race(CANCEL_RACE_CALIBRATION + $round, $offset);
            [$cancel, $accept] = [$result['cancel'], $result['accept']];

            if ($result['ok']) {
                $outcomes[$result['state']]++;
            } else {
                $wrong["round {$round}"] = $result['detail'];
            }

            ($estimate = cancelRaceMeetingOffset($cancel, $accept)) === null || $estimates[] = $estimate;
            $pids[] = [$cancel['pid'] ?? 0, $accept['pid'] ?? 0];
            $interleaved += (int) cancelRaceInterleaved($cancel, $accept);
            $waited += (int) collect([$cancel, $accept])->flatMap(fn($answer) => $answer['events'] ?? [])->contains(fn($event) => $event['waited_ms'] >= 10);
        }

        if (getenv('RACE_VERBOSE')) {
            fwrite(STDERR, sprintf(
                "\ncancel race: %d rounds (+%d to measure), start offset re-centred every round (last %d ms) with a swing of +/-40 ms; %d rounds where one request asked for a lock while the other held the next one; %d rounds with a lock wait of 10 ms or more; outcomes %s\n",
                CANCEL_RACE_ROUNDS, CANCEL_RACE_CALIBRATION, $lastCentre, $interleaved, $waited, json_encode($outcomes),
            ));
        }

        expect($wrong)->toBe([])
            ->and(collect($pids)->every(fn($pair) => $pair[0] > 0 && $pair[1] > 0 && $pair[0] !== $pair[1] && ! in_array(getmypid(), $pair, true)))->toBeTrue()
            ->and($interleaved)->toBeGreaterThanOrEqual(CANCEL_RACE_MIN_INTERLEAVED);
    } finally {
        Artisan::call('migrate:fresh', ['--force' => true]);
    }
})->group('sequential-pg');
