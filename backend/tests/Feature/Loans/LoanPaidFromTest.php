<?php

// Task 2 of credit-score-and-loan-use: a purchase record can say it was paid from a loan.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\FarmUnit;
use App\Models\Transaction;

beforeEach(function () {
    buildLoanWorld($this);

    $this->unitA = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id, 'community_id' => $this->profile->community_id]);
    $this->unitB = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id, 'community_id' => $this->profile->community_id]);

    $this->loan = ($this->openLoan)(['amount' => '500', 'farms' => [
        ['farm_unit' => $this->unitA->uuid, 'amount' => '500'],
    ]]);

    $this->buy = fn(array $over = []) => $this->actingAs($this->farmerUser)->post('/my-records', array_merge([
        'transaction_template_id' => $this->feedTemplate->id, 'amount' => '120', 'settlement_account_id' => $this->cash->id,
        'transaction_date' => now()->toDateString(), 'farm_unit_id' => $this->unitA->id, 'loan' => $this->loan->uuid, 'narration' => 'loan-test',
    ], $over));
});

test('a purchase paid from a loan is linked to it', function () {
    ($this->buy)()->assertSessionHasNoErrors();

    expect(Transaction::where('narration', 'loan-test')->firstOrFail()->loan_id)->toBe($this->loan->id);
});

test('a purchase without the choice has no loan', function () {
    ($this->buy)(['loan' => null])->assertSessionHasNoErrors();

    expect(Transaction::where('narration', 'loan-test')->firstOrFail()->loan_id)->toBeNull();
});

test('another farmer loan, an existing loan and a cancelled loan are refused', function (string $case) {
    $loan = match ($case) {
        'not yours' => (function () {
            $other = app(\App\Services\Loans\LoanService::class)->record($this->otherFarmer, $this->otherFarmerUser, ($this->moneyEntry)(['amount' => '100']));

            return $other;
        })(),
        'existing' => app(\App\Services\Loans\LoanService::class)->record($this->profile, $this->farmerUser, ($this->existingEntry)()),
        'cancelled' => tap(($this->openLoan)(), fn($l) => $l->update(['status' => 'cancelled'])),
    };

    ($this->buy)(['loan' => $loan->uuid])->assertSessionHasErrors(['loan' => 'Please choose one of your loans that is still active.']);

    expect(Transaction::where('narration', 'loan-test')->count())->toBe(0);
})->with(['not yours', 'existing', 'cancelled']);

test('a purchase paid from a loan needs a farm', function () {
    ($this->buy)(['farm_unit_id' => null])->assertSessionHasErrors(['loan' => 'Please choose the farm this was bought for.']);
});

test('a farm that is not on the loan is refused', function () {
    ($this->buy)(['farm_unit_id' => $this->unitB->id])->assertSessionHasErrors(['loan' => 'This farm is not on that loan.']);
});

test('a credit purchase cannot be paid from a loan', function () {
    $this->feedTemplate->update(['allows_credit' => true]);

    ($this->buy)(['is_credit' => true, 'settlement_account_id' => null])
        ->assertSessionHasErrors(['loan' => 'A purchase on credit cannot be paid from a loan.']);
});

test('the loan page gives planned, accounted and not yet accounted for each farm', function () {
    ($this->buy)(['amount' => '120'])->assertSessionHasNoErrors();
    ($this->buy)(['amount' => '80', 'narration' => 'other'])->assertSessionHasNoErrors();

    $this->actingAs($this->farmerUser)->get("/loans/{$this->loan->uuid}")->assertInertia(function ($page) {
        expect($page->toArray()['props']['farms'])->toBe([
            ['uuid' => $this->unitA->uuid, 'name' => $this->unitA->name, 'purpose' => null, 'planned' => 50000, 'accounted' => 20000, 'not_yet_accounted' => 30000, 'over' => 0],
        ]);
    });
});

test('a purchase above the plan is allowed and shown as over', function () {
    ($this->buy)(['amount' => '600'])->assertSessionHasNoErrors();

    $this->actingAs($this->farmerUser)->get("/loans/{$this->loan->uuid}")->assertInertia(function ($page) {
        $farm = $page->toArray()['props']['farms'][0];

        expect([$farm['not_yet_accounted'], $farm['over']])->toBe([0, 10000]);
    });
});

test('a cancelled purchase is not counted as accounted', function () {
    ($this->buy)()->assertSessionHasNoErrors();
    $bought = Transaction::where('narration', 'loan-test')->firstOrFail();
    $bought->replicate(['uuid', 'reference', 'idempotency_key'])->fill(['reverses_transaction_id' => $bought->id, 'narration' => 'undo', 'transaction_type' => Transaction::ADJUSTMENT])->save();

    $this->actingAs($this->farmerUser)->get("/loans/{$this->loan->uuid}")->assertInertia(
        fn($page) => expect($page->toArray()['props']['farms'][0]['accounted'])->toBe(0)
    );
});

test('the record form offers the farmer active money loans by uuid, and never a numeric id', function () {
    $this->actingAs($this->farmerUser)->get('/my-records/create')->assertOk()->assertInertia(function ($page) {
        $loans = $page->toArray()['props']['loans'];

        expect(array_column($loans, 'uuid'))->toContain($this->loan->uuid);
        expect(array_keys($loans[0]))->toBe(['uuid', 'lender', 'amount', 'date']);
    });
});

test('a record that needs no farm can still be paid from a loan, and counts on the farm named', function () {
    $labour = $this->feedTemplate->replicate();
    $labour->fill(['name' => 'Labour cost', 'slug' => 'labour-cost-test', 'requires_farm_unit' => false])->save();

    ($this->buy)(['transaction_template_id' => $labour->id])->assertSessionHasNoErrors();

    $row = Transaction::where('narration', 'loan-test')->firstOrFail();
    expect([$row->loan_id, $row->farm_unit_id])->toBe([$this->loan->id, $this->unitA->id]);

    $this->actingAs($this->farmerUser)->get("/loans/{$this->loan->uuid}")->assertInertia(
        fn($page) => expect($page->toArray()['props']['farms'][0]['accounted'])->toBe(12000)
    );
});

test('a record that needs no farm and is not from a loan keeps no farm', function () {
    $labour = $this->feedTemplate->replicate();
    $labour->fill(['name' => 'Labour cost', 'slug' => 'labour-cost-test', 'requires_farm_unit' => false])->save();

    ($this->buy)(['transaction_template_id' => $labour->id, 'loan' => null])->assertSessionHasNoErrors();

    expect(Transaction::where('narration', 'loan-test')->firstOrFail()->farm_unit_id)->toBeNull();
});
