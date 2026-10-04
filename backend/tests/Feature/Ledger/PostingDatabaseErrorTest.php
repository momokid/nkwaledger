<?php

use App\Exceptions\Ledger\PostingFailed;
use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\Notification;
use App\Models\SyncSubmission;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

const GENERIC = 'Something went wrong. Please try again.';
const LEAK = 'SECRET-BIND-123';

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

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);
});

// makes inserts into transactions fail like a real database error would, optionally only one amount
function failingInserts(Closure $run, ?int $onlyAmountMinor = null): void
{
    $fail = true;

    Transaction::creating(function (Transaction $transaction) use (&$fail, $onlyAmountMinor) {
        if ($fail && ($onlyAmountMinor === null || $transaction->amount_minor === $onlyAmountMinor)) {
            throw new QueryException('testing', 'insert into "transactions" ("farmer_profile_id") values (?)', [LEAK], new Exception('SQLSTATE[23000]: Integrity constraint violation'));
        }
    });

    try {
        $run();
    } finally {
        $fail = false;
    }
}

function webPayload(): array
{
    return [
        'transaction_template_id' => test()->template->id,
        'amount' => '100',
        'settlement_account_id' => test()->cash->id,
        'transaction_date' => now()->toDateString(),
        'idempotency_key' => (string) Str::uuid(),
    ];
}

function syncRecord(): array
{
    return [
        'uuid' => (string) Str::uuid(),
        'template' => test()->template->id,
        'farmer' => test()->profile->uuid,
        'amount' => '100',
        'settlement_account_id' => test()->cash->id,
        'event_date' => now()->toDateString(),
        'device_created_at' => now()->toIso8601String(),
    ];
}

test('a database error on the web record route gives a 503 with only the generic text', function () {
    failingInserts(function () {
        $this->actingAs($this->farmerUser)->postJson('/my-records', webPayload())
            ->assertStatus(503)
            ->assertExactJson(['message' => GENERIC]);
    });

    expect(Transaction::count())->toBe(0);
});

test('a business rule failure on the web record route is still a 422', function () {
    $this->actingAs($this->farmerUser)->postJson('/my-records', ['transaction_date' => now()->subYears(5)->toDateString()] + webPayload())
        ->assertStatus(422)
        ->assertExactJson(['message' => 'There is no accounting period covering that date.']);
});

test('a database error on sync is an error for that record, stores nothing and notifies nobody', function () {
    failingInserts(function () {
        $response = $this->actingAs($this->farmerUser)->postJson('/sync/submissions', ['records' => [syncRecord()]])->assertOk();

        expect($response->json('results.0.status'))->toBe('error')
            ->and($response->json('results.0.error'))->toBe(GENERIC);

        foreach (['insert into', 'transactions', LEAK, 'SQLSTATE'] as $raw) {
            expect($response->getContent())->not->toContain($raw);
        }
    });

    expect(SyncSubmission::count())->toBe(0)->and(Notification::count())->toBe(0)->and(Transaction::count())->toBe(0);
});

test('after the fault clears the same uuid is accepted and posts once', function () {
    $record = syncRecord();

    failingInserts(fn() => $this->actingAs($this->farmerUser)->postJson('/sync/submissions', ['records' => [$record]])->assertOk());

    $retry = $this->actingAs($this->farmerUser)->postJson('/sync/submissions', ['records' => [$record]])->json('results.0');
    $again = $this->actingAs($this->farmerUser)->postJson('/sync/submissions', ['records' => [$record]])->json('results.0');

    expect($retry['status'])->toBe('accepted')->and($again)->toBe($retry)->and(Transaction::count())->toBe(1);
});

test('a system failure on the second record does not block the first and third', function () {
    $records = [syncRecord(), ['amount' => '200'] + syncRecord(), syncRecord()];

    failingInserts(function () use ($records) {
        $results = $this->actingAs($this->farmerUser)->postJson('/sync/submissions', ['records' => $records])->assertOk()->json('results');

        expect(array_column($results, 'uuid'))->toBe(array_column($records, 'uuid'))
            ->and($results[0]['status'])->toBe('accepted')
            ->and($results[1]['status'])->toBe('error')
            ->and($results[2]['status'])->toBe('accepted');
    }, 20000);

    expect(Transaction::count())->toBe(2)->and(SyncSubmission::count())->toBe(2);
});

test('a business rule failure on sync is still stored as needs fixing', function () {
    $result = $this->actingAs($this->farmerUser)->postJson('/sync/submissions', ['records' => [['event_date' => now()->addDay()->toDateString()] + syncRecord()]])->json('results.0');

    expect($result['status'])->toBe('needs_fixing')
        ->and($result['reason'])->toBe('That date has not happened yet.')
        ->and(SyncSubmission::first()->status)->toBe('needs_fixing');
});

test('an admin approve that hits a database error leaves the row held and a later approve works', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->farmerUser->update(['is_active' => false]);
    $record = syncRecord();
    $this->actingAs($this->farmerUser)->postJson('/sync/submissions', ['records' => [$record]])->assertOk();
    $id = SyncSubmission::first()->id;

    failingInserts(function () use ($admin, $id) {
        $response = $this->actingAs($admin)->postJson("/admin/sync-submissions/{$id}/approve")
            ->assertStatus(503)
            ->assertExactJson(['message' => GENERIC]);

        expect($response->getContent())->not->toContain(LEAK);
    });

    $row = SyncSubmission::first();

    expect($row->status)->toBe('held_for_review')->and($row->reviewed_by)->toBeNull()->and(Transaction::count())->toBe(0);

    $this->actingAs($admin)->postJson("/admin/sync-submissions/{$id}/approve")->assertOk();

    expect(SyncSubmission::first()->status)->toBe('accepted')->and(Transaction::count())->toBe(1);
});

test('the real database exception is logged with only the template and farmer ids', function () {
    Log::spy();

    failingInserts(function () {
        $this->actingAs($this->farmerUser)->postJson('/my-records', webPayload());
    });

    Log::shouldHaveReceived('error')->withArgs(function ($message, $context) {
        return $context['template_id'] === $this->template->id
            && $context['farmer_id'] === $this->profile->id
            && $context['exception'] instanceof QueryException
            && str_contains($context['exception']->getMessage(), LEAK)
            && array_keys($context) === ['template_id', 'farmer_id', 'exception'];
    })->once();
});

test('a business rule message is passed through unchanged', function () {
    $request = new PostingRequest(
        farmerProfileId: $this->profile->id,
        transactionTemplateId: $this->template->id,
        amount: '100',
        settlementAccountId: $this->cash->id,
        transactionDate: now()->addDay()->toDateString(),
        recordedBy: $this->farmerUser->id,
    );

    expect(fn() => app(PostingService::class)->post($request))->toThrow(PostingFailed::class, 'That date has not happened yet.');
});
