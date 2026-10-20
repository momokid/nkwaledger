<?php

// Task 3.6b, part A: the SMS code that lets a farmer accept an agent's entry without the app.
// 6 digits, kept only as a keyed hash, 10 minutes, one use, 3 wrong tries, 3 sends an hour.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Contracts\SmsProvider;
use App\Exceptions\Loans\EntryRefused;
use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\Notification;
use App\Models\PendingEntry;
use App\Models\PendingEntryCode;
use App\Services\Sms\ArkeselSmsProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;

beforeEach(function () {
    // a code lives for minutes, so the clock stands still until a test moves it
    $this->freezeTime();
    buildLoanWorld($this);

    $this->entry = ($this->pendingMoney)();
    $this->sms = fn() => app(SmsProvider::class)->sent;
    $this->wrongCode = fn(string $real) => $real === '000000' ? '000001' : '000000';
});

test('sending texts the farmer the exact words, with the amount in cedis and the lender', function () {
    $this->codes->send($this->entry, $this->agent);

    $sent = ($this->sms)();
    $code = ($this->lastCode)();

    expect($sent)->toHaveCount(1)
        ->and($sent[0]['phone'])->toBe($this->profile->user->phone)
        ->and($code)->toMatch('/^\d{6}$/')
        ->and($sent[0]['message'])->toBe("NkwaLedger: Your agent recorded a loan of GHS 500.00 from Sika Microfinance. Give the code {$code} to your agent only if you agree. It works for 10 minutes.");
});

test('the code is kept only as a keyed hash, nowhere in plain text', function () {
    $this->codes->send($this->entry, $this->agent);
    $code = ($this->lastCode)();

    $row = PendingEntryCode::first();

    expect($row->code_hash)->toMatch('/^[0-9a-f]{64}$/')->not->toContain($code);

    foreach (['pending_entry_codes', 'pending_entries', 'audit_logs', 'notifications', 'loans'] as $table) {
        expect(json_encode(DB::table($table)->get()))->not->toContain($code, "the code is in {$table}");
    }
});

test('a correct code accepts the entry and makes the loan, recorded as accepted by code', function () {
    $this->codes->send($this->entry, $this->agent);

    $loan = $this->codes->enter($this->entry, $this->agent, ($this->lastCode)());

    expect($loan->accepted_via)->toBe('code')
        ->and((int) $loan->recorded_by)->toBe($this->agent->id)
        ->and($this->entry->fresh()->status)->toBe('accepted')
        ->and($this->entry->fresh()->decided_via)->toBe('code')
        ->and(PendingEntryCode::first()->used_at)->not->toBeNull();

    $audit = AuditLog::where('auditable_type', PendingEntry::class)->get()->sortBy('id')->pluck('action')->values()->all();

    expect($audit)->toContain('pending_entry.code_sent')->toContain('pending_entry.accepted');
});

test('the code works for 10 minutes and not a moment longer', function () {
    $this->codes->send($this->entry, $this->agent);
    $code = ($this->lastCode)();

    $this->travel(9)->minutes();
    $this->travel(59)->seconds();
    expect($this->codes->check($this->entry, $code))->toBe('ok');
});

test('an expired code is refused with the approved words', function () {
    $this->codes->send($this->entry, $this->agent);
    $code = ($this->lastCode)();

    $this->travel(10)->minutes();
    $this->travel(1)->seconds();

    expect($this->codes->check($this->entry, $code))->toBe('expired')
        ->and(fn() => $this->codes->enter($this->entry, $this->agent, $code))->toThrow(EntryRefused::class, 'The code has expired. Send a new code.')
        ->and(Loan::count())->toBe(0);
});

test('a code is used once', function () {
    $this->codes->send($this->entry, $this->agent);
    $code = ($this->lastCode)();

    expect($this->codes->check($this->entry, $code))->toBe('ok')
        ->and($this->codes->check($this->entry, $code))->toBe('expired');
});

test('a new code cancels the earlier one', function () {
    $this->codes->send($this->entry, $this->agent);
    $old = ($this->lastCode)();
    $this->codes->send($this->entry, $this->agent);
    $new = ($this->lastCode)();

    expect(PendingEntryCode::whereNotNull('cancelled_at')->count())->toBe(1);

    if ($old !== $new) {
        expect($this->codes->check($this->entry, $old))->toBe('wrong');
    }

    expect($this->codes->check($this->entry, $new))->toBe('ok');
});

test('a wrong code is refused with the approved words, and three wrong tries lock that code', function () {
    $this->codes->send($this->entry, $this->agent);
    $code = ($this->lastCode)();
    $wrong = ($this->wrongCode)($code);

    expect(fn() => $this->codes->enter($this->entry, $this->agent, $wrong))->toThrow(EntryRefused::class, 'The code is not correct.')
        ->and(fn() => $this->codes->enter($this->entry, $this->agent, $wrong))->toThrow(EntryRefused::class, 'The code is not correct.')
        ->and(fn() => $this->codes->enter($this->entry, $this->agent, $wrong))->toThrow(EntryRefused::class, 'Too many wrong tries. Send a new code.');

    // even the right code is no good now
    expect(fn() => $this->codes->enter($this->entry, $this->agent, $code))->toThrow(EntryRefused::class, 'Too many wrong tries. Send a new code.')
        ->and([Loan::count(), $this->entry->fresh()->status])->toBe([0, 'pending']);

    // a fresh code starts clean
    $this->codes->send($this->entry, $this->agent);

    expect($this->codes->enter($this->entry, $this->agent, ($this->lastCode)()))->toBeInstanceOf(Loan::class);
});

test('entering a code when none was sent says it has expired', function () {
    expect(fn() => $this->codes->enter($this->entry, $this->agent, '123456'))->toThrow(EntryRefused::class, 'The code has expired. Send a new code.');
});

test('at most three codes can be sent per entry per hour', function () {
    $this->codes->send($this->entry, $this->agent);
    $this->codes->send($this->entry, $this->agent);
    $this->codes->send($this->entry, $this->agent);

    expect(fn() => $this->codes->send($this->entry, $this->agent))->toThrow(EntryRefused::class)
        ->and(($this->sms)())->toHaveCount(3);

    // another entry has its own allowance
    $other = ($this->pendingExisting)();
    $this->codes->send($other, $this->agent);

    expect(($this->sms)())->toHaveCount(4);

    $this->travel(61)->minutes();
    $this->codes->send($this->entry, $this->agent);

    expect(($this->sms)())->toHaveCount(5);
});

test('a farmer with no phone number cannot be sent a code', function () {
    $this->profile->user->forceFill(['phone' => ''])->save();

    expect(fn() => $this->codes->send($this->entry->fresh(), $this->agent))->toThrow(EntryRefused::class)
        ->and(($this->sms)())->toBe([])
        ->and(PendingEntryCode::count())->toBe(0);
});

test('a code cannot be sent for an entry that is no longer pending', function () {
    $this->entries->withdraw($this->entry, $this->agent);

    expect(fn() => $this->codes->send($this->entry->fresh(), $this->agent))->toThrow(EntryRefused::class)
        ->and(PendingEntryCode::count())->toBe(0);
});

test('a code accepted after a refused acceptance is still good (the entry stayed pending)', function () {
    $entry = ($this->pendingMoney)(['date' => now()->subDay()->toDateString()]);
    $this->codes->send($entry, $this->agent);
    $code = ($this->lastCode)();

    \App\Models\AccountingPeriod::first()->close($this->admin);

    expect(fn() => $this->codes->enter($entry, $this->agent, $code))->toThrow(\App\Exceptions\Ledger\PostingFailed::class, 'That period is closed. Record it against today instead.')
        ->and($entry->fresh()->status)->toBe('pending')
        ->and(PendingEntryCode::where('pending_entry_id', $entry->id)->first()->used_at)->toBeNull();
});

test('the code is in no log, no error, no notification and no flash, on any path', function () {
    $handler = new TestHandler();
    Log::getLogger()->pushHandler($handler);

    // the real Arkesel path, with the provider answering a failure and then a success
    $this->app->instance(SmsProvider::class, new ArkeselSmsProvider('key', 'NkwaLedger'));
    Http::fakeSequence()->push(['message' => 'down'], 500)->push(['status' => 'success'], 200);
    $this->codes = app(\App\Services\Loans\EntryCodeService::class);

    $failure = null;

    try {
        $this->codes->send($this->entry, $this->agent);
    } catch (Throwable $e) {
        $failure = $e;
    }

    $codeRow = PendingEntryCode::latest('id')->first();
    expect($failure)->not->toBeNull();

    $this->codes->send($this->entry, $this->agent);

    $recorded = collect(Http::recorded())->map(fn($pair) => $pair[0]->data()['message'])->last();
    preg_match('/code (\d{6}) to your agent/', $recorded, $match);
    $code = $match[1];

    // wrong tries, a correct try, and a repeat
    try {
        $this->codes->enter($this->entry, $this->agent, '000000' === $code ? '111111' : '000000');
    } catch (EntryRefused) {
    }

    $this->codes->enter($this->entry, $this->agent, $code);

    $everything = json_encode([
        array_map(fn($record) => [$record['message'], $record['context']], $handler->getRecords()),
        $failure->getMessage(),
        Notification::all(),
        AuditLog::all(),
    ]);

    expect($everything)->not->toContain($code);
});

test('a long lender name is cut in the loan SMS so it fits 160 characters, and the rest of the words and the code stay whole', function () {
    $name = mb_substr(str_repeat('Asante Akim North Rural Bank ', 9), 0, 255);
    $entry = ($this->pendingMoney)(['lender_name' => $name]);

    $this->codes->send($entry, $this->agent);

    $sent = ($this->sms)()[0]['message'];
    $code = ($this->lastCode)();

    expect(mb_strlen($sent))->toBeLessThanOrEqual(160)
        ->and($sent)->toStartWith('NkwaLedger: Your agent recorded a loan of GHS 500.00 from Asante')
        ->and($sent)->toEndWith("... Give the code {$code} to your agent only if you agree. It works for 10 minutes.")
        ->and($entry->lenderName())->toBe($name);
});
