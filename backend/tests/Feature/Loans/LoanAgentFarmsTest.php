<?php

// Task 4 of credit-score-and-loan-use: an agent's money loan names the farms too, and keeps them until the farmer accepts.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\FarmUnit;
use App\Models\Loan;
use App\Models\PendingEntry;

beforeEach(function () {
    buildLoanWorld($this);

    $this->unitA = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id, 'community_id' => $this->profile->community_id]);
    $this->unitB = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id, 'community_id' => $this->profile->community_id]);
    $this->foreignUnit = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->otherFarmer->id, 'community_id' => $this->otherFarmer->community_id]);

    $this->url = "/agent/farmers/{$this->profile->uuid}";
    $this->body = fn(array $over = []) => array_merge([
        'kind' => 'money', 'lender' => institutionNamed('Sika Microfinance', 'microfinance')->uuid,
        'amount' => '500', 'date' => now()->toDateString(), 'paid_into' => $this->cash->id, 'idempotency_key' => 'k-' . uniqid(),
        'farms' => [
            ['farm_unit' => $this->unitA->uuid, 'amount' => '300'],
            ['farm_unit' => $this->unitB->uuid, 'amount' => '200'],
        ],
    ], $over);
    $this->record = fn(array $over = []) => $this->actingAs($this->agent)->post("{$this->url}/loans", ($this->body)($over));
});

test('the agent money form offers the farmer farm units by uuid, and the existing form none', function () {
    $this->actingAs($this->agent)->get("{$this->url}/loans/create")->assertInertia(function ($page) {
        $farms = $page->toArray()['props']['farms'];

        expect(array_column($farms, 'uuid'))->toContain($this->unitA->uuid, $this->unitB->uuid)->not->toContain($this->foreignUnit->uuid);
        expect(array_keys($farms[0]))->toBe(['uuid', 'name']);
    });

    $this->actingAs($this->agent)->get("{$this->url}/loans/existing/create")->assertInertia(
        fn($page) => expect($page->toArray()['props']['farms'] ?? [])->toBe([])
    );
});

test('the farms are kept on the waiting entry, as uuids and pesewas', function () {
    ($this->record)()->assertSessionHasNoErrors()->assertRedirect($this->url);

    expect(PendingEntry::firstOrFail()->payload['farms'])->toBe([
        ['farm_unit' => $this->unitA->uuid, 'amount_minor' => 30000],
        ['farm_unit' => $this->unitB->uuid, 'amount_minor' => 20000],
    ]);
});

test('farms that are missing, not the farmer own, repeated, zero or not adding up are refused', function (array $over) {
    ($this->record)($over)->assertSessionHasErrors();

    expect(PendingEntry::count())->toBe(0);
})->with([
    'missing' => [['farms' => null]],
    'empty' => [['farms' => []]],
    'another farmer' => [fn() => ['farms' => [['farm_unit' => test()->foreignUnit->uuid, 'amount' => '500']]]],
    'twice' => [fn() => ['farms' => [['farm_unit' => test()->unitA->uuid, 'amount' => '250'], ['farm_unit' => test()->unitA->uuid, 'amount' => '250']]]],
    'zero' => [fn() => ['farms' => [['farm_unit' => test()->unitA->uuid, 'amount' => '500'], ['farm_unit' => test()->unitB->uuid, 'amount' => '0']]]],
    'not adding up' => [['amount' => '600']],
]);

test('an existing loan needs no farms', function () {
    $this->actingAs($this->agent)->post("{$this->url}/loans", [
        'kind' => 'existing', 'lender' => institutionNamed('Uncle Kofi', 'other')->uuid, 'amount' => '300', 'idempotency_key' => 'e1',
    ])->assertSessionHasNoErrors()->assertRedirect($this->url);

    expect(PendingEntry::count())->toBe(1);
});

test('accepting in the app makes the loan with its farm amounts', function () {
    ($this->record)()->assertSessionHasNoErrors();
    $entry = PendingEntry::firstOrFail();

    $this->actingAs($this->farmerUser)->post("/loans/entries/{$entry->uuid}/accept")->assertRedirect('/loans');

    $loan = Loan::firstOrFail();
    expect($loan->farmAllocations()->pluck('amount_minor', 'farm_unit_id')->all())->toBe([$this->unitA->id => 30000, $this->unitB->id => 20000]);
    expect($loan->unaccountedMinor())->toBe(50000);
});

test('accepting with the SMS code makes the loan with its farm amounts', function () {
    ($this->record)()->assertSessionHasNoErrors();
    $entry = PendingEntry::firstOrFail();

    $this->actingAs($this->agent)->post("/agent/loan-entries/{$entry->uuid}/send-code")->assertRedirect();
    $this->actingAs($this->agent)->post("/agent/loan-entries/{$entry->uuid}/code", ['code' => ($this->lastCode)()])->assertSessionHasNoErrors();

    expect(Loan::firstOrFail()->farmAllocations()->count())->toBe(2);
});

test('an entry recorded before farms were asked is still accepted, with no farm amounts', function () {
    $entry = ($this->pendingMoney)();

    $this->actingAs($this->farmerUser)->post("/loans/entries/{$entry->uuid}/accept")->assertRedirect('/loans');

    expect(Loan::firstOrFail()->farmAllocations()->count())->toBe(0);
});
