<?php

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\SyncSubmission;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\SyncSubmissionService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// SQLite cannot run two requests at once, so the race is simulated: a listener fires right
// after the "already stored?" lookup for one uuid and lets a competing submission finish,
// which is exactly the gap between that lookup and the insert
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $dr = LedgerClass::create(['name' => 'Dr']);
    $cr = LedgerClass::create(['name' => 'Cr']);
    $assetSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Assets', 'class_id' => $dr->id])->id, 'name' => 'Money']);
    $incomeSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Income', 'class_id' => $cr->id])->id, 'name' => 'Farm Income']);
    $control = LedgerControl::create(['name' => 'General']);
    $type = LedgerType::create(['name' => 'GL']);

    $this->cash = LedgerAccount::create(['name' => 'Cash', 'control_id' => $control->id, 'subcategory_id' => $assetSub->id, 'type_id' => $type->id, 'is_settlement' => true]);
    $sales = LedgerAccount::create(['name' => 'Sales', 'control_id' => $control->id, 'subcategory_id' => $incomeSub->id, 'type_id' => $type->id]);
    $this->template = TransactionTemplate::create([
        'name' => 'I sold crops', 'slug' => 'crop_sale', 'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id, 'credit_account_id' => $sales->id, 'settlement_side' => 'debit',
    ]);
    AccountingPeriod::create(['name' => 'Test', 'starts_on' => now()->startOfYear()->toDateString(), 'ends_on' => now()->endOfYear()->toDateString()]);

    $this->userA = User::factory()->create();
    $this->userA->assignRole('farmer');
    $this->profileA = FarmerProfile::factory()->create(['user_id' => $this->userA->id]);
    $this->userB = User::factory()->create();
    $this->userB->assignRole('farmer');
    $this->profileB = FarmerProfile::factory()->create(['user_id' => $this->userB->id]);
});

function raceRecord(FarmerProfile $profile, array $o = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'template' => test()->template->id,
        'farmer' => $profile->uuid,
        'amount' => '100',
        'settlement_account_id' => test()->cash->id,
        'event_date' => now()->toDateString(),
        'device_created_at' => now()->toIso8601String(),
    ], $o);
}

function raceSync(User $user, array ...$records)
{
    return test()->actingAs($user)->postJson('/sync/submissions', ['records' => $records])->assertOk()->json('results');
}

// right after the first "already stored?" lookup for $uuid, runs $competitor once
function racingOn(string $uuid, Closure $competitor, Closure $run): void
{
    $active = true;
    $raced = false;

    DB::listen(function ($query) use (&$active, &$raced, $uuid, $competitor) {
        if ($active && ! $raced && str_contains($query->sql, 'from "sync_submissions" where "client_uuid"') && in_array($uuid, $query->bindings, true)) {
            $raced = true;
            $competitor();
        }
    });

    try {
        $run();
    } finally {
        $active = false;
    }
}

test('a competing identical submission returns the stored result, not an error', function () {
    $record = raceRecord($this->profileA);
    $competitor = fn() => app(SyncSubmissionService::class)->submit($this->userA, $record);

    racingOn($record['uuid'], $competitor, function () use ($record) {
        $result = raceSync($this->userA, $record)[0];
        $stored = SyncSubmission::first();

        expect($result)->not->toHaveKey('error')
            ->and($result['status'])->toBe('accepted')
            ->and($result['reference'])->toBe($stored->transaction->reference);
    });

    expect(Transaction::where('idempotency_key', "sync.{$this->userA->id}.{$record['uuid']}")->count())->toBe(1)
        ->and(Transaction::count())->toBe(1)
        ->and(SyncSubmission::count())->toBe(1);
});

test('a competing row from another user with the same uuid gives the generic error and nothing of theirs', function () {
    $record = raceRecord($this->profileA);
    $theirs = ['farmer' => $this->profileB->uuid] + $record;
    $competitor = fn() => app(SyncSubmissionService::class)->submit($this->userB, $theirs);

    racingOn($record['uuid'], $competitor, function () use ($record) {
        $result = raceSync($this->userA, $record)[0];

        expect($result)->toBe(['uuid' => $record['uuid'], 'status' => 'error', 'error' => 'Something went wrong. Please try again.']);
    });

    $row = SyncSubmission::first();

    expect(SyncSubmission::count())->toBe(1)->and($row->user_id)->toBe($this->userB->id)->and(Transaction::count())->toBe(1);
});

test('a different database error on the insert still returns the error result', function () {
    $record = raceRecord($this->profileA);
    $active = true;

    SyncSubmission::creating(function () use (&$active) {
        if ($active) {
            throw new UniqueConstraintViolationException('testing', 'insert into "x"', [], new Exception('UNIQUE constraint failed: transactions.idempotency_key'));
        }
    });

    try {
        $other = raceSync($this->userA, $record)[0];
    } finally {
        $active = false;
    }

    expect($other)->toBe(['uuid' => $record['uuid'], 'status' => 'error', 'error' => 'Something went wrong. Please try again.'])
        ->and(SyncSubmission::count())->toBe(0);

    $active = true;
    SyncSubmission::creating(function () use (&$active) {
        if ($active) {
            throw new QueryException('testing', 'insert into "x"', [], new Exception('disk I/O error'));
        }
    });

    try {
        expect(raceSync($this->userA, raceRecord($this->profileA))[0]['status'])->toBe('error');
    } finally {
        $active = false;
    }
});

test('a race on record 2 does not affect records 1 and 3', function () {
    [$one, $two, $three] = [raceRecord($this->profileA), raceRecord($this->profileA), raceRecord($this->profileA)];
    $competitor = fn() => app(SyncSubmissionService::class)->submit($this->userA, $two);

    racingOn($two['uuid'], $competitor, function () use ($one, $two, $three) {
        $results = raceSync($this->userA, $one, $two, $three);

        expect(array_column($results, 'uuid'))->toBe([$one['uuid'], $two['uuid'], $three['uuid']])
            ->and(array_column($results, 'status'))->toBe(['accepted', 'accepted', 'accepted'])
            ->and($results[1])->not->toHaveKey('error');
    });

    expect(Transaction::count())->toBe(3)->and(SyncSubmission::count())->toBe(3);
});
