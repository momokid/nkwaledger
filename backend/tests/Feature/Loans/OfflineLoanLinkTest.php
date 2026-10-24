<?php

// Task 5a of credit-score-and-loan-use: a purchase saved offline keeps its "Paid from a loan" choice when it syncs.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\FarmUnit;
use App\Models\SyncSubmission;
use App\Models\Transaction;
use Illuminate\Support\Str;

beforeEach(function () {
    buildLoanWorld($this);

    $this->unitA = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id, 'community_id' => $this->profile->community_id]);
    $this->unitB = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id, 'community_id' => $this->profile->community_id]);

    $this->loan = ($this->openLoan)(['amount' => '500', 'farms' => [['farm_unit' => $this->unitA->uuid, 'amount' => '500']]]);

    $this->rec = fn(array $over = []) => array_merge([
        'uuid' => (string) Str::uuid(),
        'template' => $this->feedTemplate->id,
        'farmer' => $this->profile->uuid,
        'amount' => '120',
        'settlement_account_id' => $this->cash->id,
        'farm_unit_id' => $this->unitA->id,
        'loan' => $this->loan->uuid,
        'narration' => 'offline-loan-test',
        'event_date' => now()->toDateString(),
        'device_created_at' => now()->toIso8601String(),
    ], $over);

    $this->sync = fn(array $record) => $this->actingAs($this->farmerUser)->postJson('/sync/submissions', ['records' => [$record]])->assertOk()->json('results.0');
});

test('an offline purchase paid from a loan is linked to it when it syncs', function () {
    expect(($this->sync)(($this->rec)())['status'])->toBe('accepted');

    expect(Transaction::where('narration', 'offline-loan-test')->firstOrFail()->loan_id)->toBe($this->loan->id);
});

test('an offline purchase with no loan has none', function () {
    expect(($this->sync)(($this->rec)(['loan' => null]))['status'])->toBe('accepted');

    expect(Transaction::where('narration', 'offline-loan-test')->firstOrFail()->loan_id)->toBeNull();
});

test('a loan that is not the farmer own, unknown, or on another farm is sent back and nothing is posted', function (string $case, string $message) {
    $other = app(\App\Services\Loans\LoanService::class)->record($this->otherFarmer, $this->otherFarmerUser, ($this->moneyEntry)(['amount' => '100']));

    $over = match ($case) {
        'not yours' => ['loan' => $other->uuid],
        'unknown' => ['loan' => '6f0c8a52-0000-7000-8000-000000000000'],
        'wrong farm' => ['farm_unit_id' => $this->unitB->id],
    };

    expect(($this->sync)(($this->rec)($over))['status'])->toBe('needs_fixing');

    expect(SyncSubmission::firstOrFail()->reason)->toBe($message);
    expect(Transaction::where('narration', 'offline-loan-test')->count())->toBe(0);
})->with([
    'not yours' => ['not yours', 'Please choose one of your loans that is still active.'],
    'unknown' => ['unknown', 'Please choose one of your loans that is still active.'],
    'wrong farm' => ['wrong farm', 'This farm is not on that loan.'],
]);
