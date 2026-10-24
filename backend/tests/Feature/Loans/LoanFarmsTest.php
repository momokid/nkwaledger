<?php

// Task 1 of credit-score-and-loan-use: a new money loan from the web form names the farms it will be used on.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\FarmUnit;
use App\Models\Loan;

beforeEach(function () {
    buildLoanWorld($this);

    $this->unitA = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id, 'community_id' => $this->profile->community_id]);
    $this->unitB = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id, 'community_id' => $this->profile->community_id]);
    $this->foreignUnit = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->otherFarmer->id, 'community_id' => $this->otherFarmer->community_id]);

    $this->body = fn(array $over = []) => array_merge([
        'kind' => 'money', 'lender_name' => 'Sika Microfinance', 'lender' => institutionNamed('Sika Microfinance', 'microfinance')->uuid,
        'amount' => '500', 'date' => now()->toDateString(), 'paid_into' => $this->cash->id, 'idempotency_key' => 'k-' . uniqid(),
        'farms' => [
            ['farm_unit' => $this->unitA->uuid, 'amount' => '300'],
            ['farm_unit' => $this->unitB->uuid, 'amount' => '200'],
        ],
    ], $over);
});

test('a farm unit has a uuid', function () {
    expect($this->unitA->uuid)->toBeString()->not->toBeEmpty();
});

test('the money form offers the farmer own farm units by uuid, and never a numeric id', function () {
    $this->actingAs($this->farmerUser)->get('/loans/create')->assertOk()->assertInertia(function ($page) {
        $farms = $page->toArray()['props']['farms'];
        $uuids = array_column($farms, 'uuid');

        expect($uuids)->toContain($this->unitA->uuid, $this->unitB->uuid)->not->toContain($this->foreignUnit->uuid);
        expect(array_keys($farms[0]))->toBe(['uuid', 'name']);
    });
});

test('farm amounts that add up are saved with the loan', function () {
    $this->actingAs($this->farmerUser)->post('/loans', ($this->body)())->assertRedirect('/loans');

    $loan = Loan::firstOrFail();
    expect($loan->farmAllocations()->orderBy('amount_minor')->pluck('amount_minor', 'farm_unit_id')->all())
        ->toBe([$this->unitB->id => 20000, $this->unitA->id => 30000]);
});

test('farm amounts that do not add up are refused and no loan is made', function () {
    $this->actingAs($this->farmerUser)->post('/loans', ($this->body)(['amount' => '600']))
        ->assertSessionHasErrors(['farms' => 'The farm amounts must add up to the loan amount.']);

    expect(Loan::count())->toBe(0);
});

test('a money loan with no farm is refused', function (mixed $farms) {
    $this->actingAs($this->farmerUser)->post('/loans', ($this->body)(['farms' => $farms]))->assertSessionHasErrors('farms');

    expect(Loan::count())->toBe(0);
})->with(['missing' => [null], 'empty' => [[]]]);

test('another farmer farm unit, a repeated farm and a zero amount are refused', function (array $farms) {
    $farms = array_map(fn($row) => ['farm_unit' => is_callable($row[0]) ? ($row[0])($this)->uuid : $row[0], 'amount' => $row[1]], $farms);

    $this->actingAs($this->farmerUser)->post('/loans', ($this->body)(['farms' => $farms]))->assertSessionHasErrors();

    expect(Loan::count())->toBe(0);
})->with([
    'not yours' => [[[fn($t) => $t->foreignUnit, '500']]],
    'unknown' => [[['6f0c8a52-0000-7000-8000-000000000000', '500']]],
    'twice' => [[[fn($t) => $t->unitA, '250'], [fn($t) => $t->unitA, '250']]],
    'zero' => [[[fn($t) => $t->unitA, '500'], [fn($t) => $t->unitB, '0']]],
]);

test('an existing loan needs no farms and gets none', function () {
    $this->actingAs($this->farmerUser)->post('/loans', ['kind' => 'existing', 'lender' => institutionNamed('Uncle Kofi', 'other')->uuid, 'amount' => '300', 'idempotency_key' => 'e1'])
        ->assertRedirect('/loans');

    expect(Loan::firstOrFail()->farmAllocations()->count())->toBe(0);
});

test('the agent form is not changed: no farms asked', function () {
    $this->actingAs($this->agent)->post("/agent/farmers/{$this->profile->uuid}/loans", [
        'kind' => 'money', 'lender' => institutionNamed('Sika Microfinance', 'microfinance')->uuid, 'amount' => '200',
        'date' => now()->toDateString(), 'paid_into' => $this->cash->id, 'idempotency_key' => 'a1',
    ])->assertRedirect();
});
