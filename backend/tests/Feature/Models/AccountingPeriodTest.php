<?php

use App\Models\AccountingPeriod;
use App\Models\User;
use Illuminate\Database\QueryException;

test('a period can be created', function () {
    $period = AccountingPeriod::create([
        'name'      => 'January 2026',
        'starts_on' => '2026-01-01',
        'ends_on'   => '2026-01-31',
    ]);

    expect($period->name)->toBe('January 2026');
    expect($period->status)->toBe('open');
});

test('the dates come back as dates', function () {
    $period = AccountingPeriod::factory()->create();

    expect($period->fresh()->starts_on)->toBeInstanceOf(Illuminate\Support\Carbon::class);
    expect($period->fresh()->ends_on)->toBeInstanceOf(Illuminate\Support\Carbon::class);
});

test('a name must be unique', function () {
    AccountingPeriod::factory()->create(['name' => 'January 2026']);

    expect(fn() => AccountingPeriod::factory()->create(['name' => 'January 2026']))
        ->toThrow(QueryException::class);
});

test('a period must end after it starts', function () {
    expect(fn() => AccountingPeriod::create([
        'name'      => 'Backwards',
        'starts_on' => '2026-01-31',
        'ends_on'   => '2026-01-01',
    ]))->toThrow(RuntimeException::class);
});

test('a new period is open', function () {
    expect(AccountingPeriod::factory()->create()->status)->toBe('open');
});

test('a period can be closed', function () {
    $admin = User::factory()->create();
    $period = AccountingPeriod::factory()->create();

    $period->close($admin);

    expect($period->fresh()->status)->toBe('closed');
    expect($period->fresh()->closed_by)->toBe($admin->id);
    expect($period->fresh()->closed_at)->not->toBeNull();
});

test('closing an already closed period is refused', function () {
    $admin = User::factory()->create();
    $period = AccountingPeriod::factory()->closed()->create();

    expect(fn() => $period->close($admin))->toThrow(RuntimeException::class);
});

test('a closed period can be reopened', function () {
    $admin = User::factory()->create();
    $period = AccountingPeriod::factory()->closed()->create();

    $period->reopen($admin);

    expect($period->fresh()->status)->toBe('open');
    expect($period->fresh()->reopened_by)->toBe($admin->id);
    expect($period->fresh()->reopened_at)->not->toBeNull();
});

test('reopening an open period is refused', function () {
    $admin = User::factory()->create();
    $period = AccountingPeriod::factory()->create();

    expect(fn() => $period->reopen($admin))->toThrow(RuntimeException::class);
});

// the closing stays visible, or a reopened period looks like it was never closed
test('reopening keeps who closed it', function () {
    $closer = User::factory()->create();
    $reopener = User::factory()->create();

    $period = AccountingPeriod::factory()->create();
    $period->close($closer);
    $period->reopen($reopener);

    expect($period->fresh()->closed_by)->toBe($closer->id);
    expect($period->fresh()->reopened_by)->toBe($reopener->id);
});

test('a reopened period can be closed again', function () {
    $admin = User::factory()->create();
    $period = AccountingPeriod::factory()->closed()->create();

    $period->reopen($admin);
    $period->close($admin);

    expect($period->fresh()->status)->toBe('closed');
});

test('a period knows who closed and reopened it', function () {
    $admin = User::factory()->create();
    $period = AccountingPeriod::factory()->create();

    $period->close($admin);

    expect($period->closedBy->is($admin))->toBeTrue();
});

// two periods covering the same day would make a transaction's home ambiguous
test('periods cannot overlap', function () {
    AccountingPeriod::factory()->create([
        'starts_on' => '2026-01-01',
        'ends_on'   => '2026-01-31',
    ]);

    expect(fn() => AccountingPeriod::factory()->create([
        'starts_on' => '2026-01-15',
        'ends_on'   => '2026-02-15',
    ]))->toThrow(RuntimeException::class);
});

test('a period butting up against another is allowed', function () {
    AccountingPeriod::factory()->create([
        'starts_on' => '2026-01-01',
        'ends_on'   => '2026-01-31',
    ]);

    $next = AccountingPeriod::factory()->create([
        'starts_on' => '2026-02-01',
        'ends_on'   => '2026-02-28',
    ]);

    expect($next->exists)->toBeTrue();
});

// a transaction dated inside a closed period cannot post, whatever today's date is
test('a period can say whether a date falls inside it', function () {
    $period = AccountingPeriod::factory()->create([
        'starts_on' => '2026-01-01',
        'ends_on'   => '2026-01-31',
    ]);

    expect($period->covers('2026-01-15'))->toBeTrue();
    expect($period->covers('2026-01-01'))->toBeTrue();
    expect($period->covers('2026-01-31'))->toBeTrue();
    expect($period->covers('2026-02-01'))->toBeFalse();
});

test('the period covering a date can be found', function () {
    $january = AccountingPeriod::factory()->create([
        'starts_on' => '2026-01-01',
        'ends_on'   => '2026-01-31',
    ]);

    AccountingPeriod::factory()->create([
        'starts_on' => '2026-02-01',
        'ends_on'   => '2026-02-28',
    ]);

    expect(AccountingPeriod::covering('2026-01-15')->is($january))->toBeTrue();
});

test('a date outside every period finds nothing', function () {
    AccountingPeriod::factory()->create([
        'starts_on' => '2026-01-01',
        'ends_on'   => '2026-01-31',
    ]);

    expect(AccountingPeriod::covering('2026-06-15'))->toBeNull();
});

test('a period can be soft deleted', function () {
    $period = AccountingPeriod::factory()->create();

    $period->delete();

    expect(AccountingPeriod::find($period->id))->toBeNull();
    expect(AccountingPeriod::withTrashed()->find($period->id))->not->toBeNull();
});
