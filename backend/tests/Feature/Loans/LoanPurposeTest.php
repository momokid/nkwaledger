<?php

// Task 4b of credit-score-and-loan-use: each farm amount on a new money loan says what the money is for.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\FarmUnit;
use App\Models\Loan;
use App\Models\PendingEntry;

beforeEach(function () {
    buildLoanWorld($this);

    $this->unitA = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id, 'community_id' => $this->profile->community_id]);
    $this->unitB = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id, 'community_id' => $this->profile->community_id]);

    $this->feedSlug = $this->feedTemplate->slug;

    $this->farms = fn(array $a = [], array $b = []) => [
        array_merge(['farm_unit' => $this->unitA->uuid, 'amount' => '300', 'purpose' => $this->feedSlug], $a),
        array_merge(['farm_unit' => $this->unitB->uuid, 'amount' => '200', 'purpose' => 'other', 'purpose_note' => 'Fencing'], $b),
    ];
    $this->body = fn(array $over = []) => array_merge([
        'kind' => 'money', 'lender' => institutionNamed('Sika Microfinance', 'microfinance')->uuid,
        'amount' => '500', 'date' => now()->toDateString(), 'paid_into' => $this->cash->id, 'idempotency_key' => 'k-' . uniqid(),
        'farms' => ($this->farms)(),
    ], $over);
    $this->postAsFarmer = fn(array $over = []) => $this->actingAs($this->farmerUser)->post('/loans', ($this->body)($over));
    $this->postAsAgent = fn(array $over = []) => $this->actingAs($this->agent)->post("/agent/farmers/{$this->profile->uuid}/loans", ($this->body)($over));
});

test('both forms offer the expense records the farmer can use, by slug and name', function () {
    $farmer = $this->actingAs($this->farmerUser)->get('/loans/create')->viewData('page')['props']['purposes'];
    $agent = $this->actingAs($this->agent)->get("/agent/farmers/{$this->profile->uuid}/loans/create")->viewData('page')['props']['purposes'];

    expect($farmer)->toBe($agent);
    expect(array_keys($farmer[0]))->toBe(['slug', 'name']);
    expect(array_column($farmer, 'slug'))->toContain($this->feedSlug)->not->toContain($this->saleTemplate->slug);
});

test('the purposes are saved with the farm amounts', function () {
    ($this->postAsFarmer)()->assertSessionHasNoErrors()->assertRedirect('/loans');

    $rows = Loan::firstOrFail()->farmAllocations()->orderBy('amount_minor')->get();

    expect([$rows[0]->purpose_template_id, $rows[0]->purpose_note])->toBe([null, 'Fencing']);
    expect([$rows[1]->purpose_template_id, $rows[1]->purpose_note])->toBe([$this->feedTemplate->id, null]);
});

test('a note typed beside a chosen record type is not kept', function () {
    ($this->postAsFarmer)(['farms' => ($this->farms)(['purpose_note' => 'ignored'])])->assertSessionHasNoErrors();

    expect(Loan::firstOrFail()->farmAllocations()->where('purpose_template_id', $this->feedTemplate->id)->firstOrFail()->purpose_note)->toBeNull();
});

test('a farm with no purpose, an unknown one, one that is not an expense record, or something else with no note is refused', function (string $case, string $key, string $message) {
    $bad = match ($case) {
        'missing' => ['purpose' => ''],
        'unknown' => ['purpose' => 'no-such-record'],
        'a sale' => ['purpose' => $this->saleTemplate->slug],
        'no note' => ['purpose' => 'other', 'purpose_note' => '  '],
    };

    ($this->postAsFarmer)(['farms' => ($this->farms)($bad)])->assertSessionHasErrors([$key => $message]);

    expect(Loan::count())->toBe(0);
})->with([
    'missing' => ['missing', 'farms.0.purpose', 'Please say what this money is for.'],
    'unknown' => ['unknown', 'farms.0.purpose', 'Please say what this money is for.'],
    'a sale' => ['a sale', 'farms.0.purpose', 'Please say what this money is for.'],
    'no note' => ['no note', 'farms.0.purpose_note', 'Please say what you will use it for.'],
]);

test('an existing loan needs no purposes', function () {
    $this->actingAs($this->farmerUser)->post('/loans', ['kind' => 'existing', 'lender' => institutionNamed('Uncle Kofi', 'other')->uuid, 'amount' => '300', 'idempotency_key' => 'e1'])
        ->assertSessionHasNoErrors();
});

test('an agent loan keeps the purposes and writes them when the farmer accepts', function () {
    ($this->postAsAgent)()->assertSessionHasNoErrors();
    $entry = PendingEntry::firstOrFail();

    expect($entry->payload['farms'][0])->toBe(['farm_unit' => $this->unitA->uuid, 'amount_minor' => 30000, 'purpose' => $this->feedSlug, 'purpose_note' => null]);

    $this->actingAs($this->farmerUser)->post("/loans/entries/{$entry->uuid}/accept")->assertRedirect('/loans');

    expect(Loan::firstOrFail()->farmAllocations()->whereNotNull('purpose_template_id')->count())->toBe(1);
    expect(Loan::firstOrFail()->farmAllocations()->whereNotNull('purpose_note')->count())->toBe(1);
});

test('an agent loan with a missing purpose is refused', function () {
    ($this->postAsAgent)(['farms' => ($this->farms)(['purpose' => ''])])->assertSessionHasErrors('farms.0.purpose');

    expect(PendingEntry::count())->toBe(0);
});

test('the loan page names the purpose beside each farm', function () {
    ($this->postAsFarmer)();
    $loan = Loan::firstOrFail();

    $farms = $this->actingAs($this->farmerUser)->get("/loans/{$loan->uuid}")->viewData('page')['props']['farms'];

    expect(array_column($farms, 'purpose', 'uuid'))->toBe([$this->unitA->uuid => $this->feedTemplate->name, $this->unitB->uuid => 'Fencing']);
});
