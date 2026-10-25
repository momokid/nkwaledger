<?php

// Task 3 of credit-score-and-loan-use: money not yet accounted for raises an alert; "something else" uses clear it;
// a reminder goes out after 7 days and then every 14 until it is cleared.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\FarmUnit;
use App\Models\LoanOtherUse;
use App\Models\Notification;

beforeEach(function () {
    buildLoanWorld($this);

    $this->unitA = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id, 'community_id' => $this->profile->community_id]);
    $this->unitB = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->profile->id, 'community_id' => $this->profile->community_id]);

    // 500 over two farms; the loan owes 500 + 200 interest
    $this->loan = ($this->openLoan)(['amount' => '500', 'farms' => [
        ['farm_unit' => $this->unitA->uuid, 'amount' => '300'],
        ['farm_unit' => $this->unitB->uuid, 'amount' => '200'],
    ]]);

    $this->buy = fn(string $amount, $unit = null) => $this->actingAs($this->farmerUser)->post('/my-records', [
        'transaction_template_id' => $this->feedTemplate->id, 'amount' => $amount, 'settlement_account_id' => $this->cash->id,
        'transaction_date' => now()->toDateString(), 'farm_unit_id' => ($unit ?? $this->unitA)->id, 'loan' => $this->loan->uuid,
    ])->assertSessionHasNoErrors();

    $this->other = fn(array $body, $by = null) => $this->actingAs($by ?? $this->farmerUser)->post("/loans/{$this->loan->uuid}/other-uses", $body);

    $this->props = fn() => $this->actingAs($this->farmerUser)->get("/loans/{$this->loan->uuid}")->viewData('page')['props'];
});

test('what is not yet accounted for is the planned amounts less the purchases and the other uses', function () {
    expect($this->loan->unaccountedMinor())->toBe(50000);

    ($this->buy)('120');
    expect($this->loan->unaccountedMinor())->toBe(38000);

    ($this->other)(['amount' => '80', 'note' => 'School fees'])->assertSessionHasNoErrors();
    expect($this->loan->fresh()->unaccountedMinor())->toBe(30000);
});

test('over-spending one farm does not hide money left on another', function () {
    ($this->buy)('400', $this->unitB);

    expect($this->loan->unaccountedMinor())->toBe(30000);
});

test('a loan fully placed, repaid or cancelled has nothing unaccounted', function () {
    ($this->other)(['amount' => '500', 'note' => 'Market stall'])->assertSessionHasNoErrors();
    expect($this->loan->fresh()->unaccountedMinor())->toBe(0);

    $second = ($this->openLoan)(['amount' => '100', 'interest' => '0', 'farms' => [['farm_unit' => $this->unitA->uuid, 'amount' => '100']]]);
    expect($second->unaccountedMinor())->toBe(10000);
    ($this->repay)($second, '100');
    expect($second->fresh()->unaccountedMinor())->toBe(0);

    $third = tap(($this->openLoan)(['amount' => '100', 'farms' => [['farm_unit' => $this->unitA->uuid, 'amount' => '100']]]), fn($l) => $l->update(['status' => 'cancelled']));
    expect($third->unaccountedMinor())->toBe(0);
});

test('a loan with no farm amounts has nothing unaccounted', function () {
    expect(($this->openLoan)()->unaccountedMinor())->toBe(0);
});

test('an other use needs a note, an amount above zero, and no more than what is left', function (array $body, string $field, string $message) {
    ($this->other)($body)->assertSessionHasErrors([$field => $message]);

    expect(LoanOtherUse::count())->toBe(0);
})->with([
    'no note' => [['amount' => '10', 'note' => ''], 'note', 'Please say what you used it for.'],
    'zero' => [['amount' => '0', 'note' => 'x'], 'amount', 'The amount needs to be more than zero.'],
    'not a number' => [['amount' => 'ten', 'note' => 'x'], 'amount', 'The amount needs to be more than zero.'],
    'too much' => [['amount' => '500.01', 'note' => 'x'], 'amount', 'That is more than what is left to place.'],
]);

test('two other uses that each fit alone but not together: the second is refused', function () {
    ($this->other)(['amount' => '300', 'note' => 'first'])->assertSessionHasNoErrors();
    ($this->other)(['amount' => '300', 'note' => 'second'])->assertSessionHasErrors(['amount' => 'That is more than what is left to place.']);

    expect(LoanOtherUse::count())->toBe(1);
});

test('only the farmer who owns the loan can add an other use', function () {
    ($this->other)(['amount' => '10', 'note' => 'x'], $this->otherFarmerUser)->assertNotFound();
    ($this->other)(['amount' => '10', 'note' => 'x'], $this->agent)->assertNotFound();

    expect(LoanOtherUse::count())->toBe(0);
});

test('the loan page carries the amount left, the other uses and no numeric id', function () {
    ($this->other)(['amount' => '80', 'note' => 'School fees']);

    $props = ($this->props)();

    expect($props['unaccounted'])->toBe(42000);
    expect($props['otherUses'])->toHaveCount(1);
    expect(array_keys($props['otherUses'][0]))->toBe(['uuid', 'note', 'amount']);
    expect($props['otherUses'][0]['note'])->toBe('School fees');
});

test('the dashboard names each loan with money not yet accounted for', function () {
    $this->actingAs($this->farmerUser)->get('/farmer/dashboard')->assertInertia(function ($page) {
        expect($page->toArray()['props']['loan_alerts'])->toBe([['uuid' => $this->loan->uuid, 'unaccounted' => 50000]]);
    });

    ($this->other)(['amount' => '500', 'note' => 'x']);

    $this->actingAs($this->farmerUser)->get('/farmer/dashboard')->assertInertia(
        fn($page) => expect($page->toArray()['props']['loan_alerts'])->toBe([])
    );
});

test('the reminder goes out after 7 days, then every 14, and stops once it is cleared', function () {
    $count = fn() => Notification::where('kind', 'loan.use_reminder')->count();
    $remind = fn() => $this->artisan('loans:remind-use')->assertSuccessful();

    $this->travelTo(now()->addDays(6));
    $remind();
    expect($count())->toBe(0);

    $this->travelTo(now()->addDay());
    $remind();
    expect($count())->toBe(1);

    $note = Notification::where('kind', 'loan.use_reminder')->firstOrFail();
    expect([$note->user_id, $note->message, $note->link])->toBe([$this->farmerUser->id, 'Please record how you used your loan.', "/loans/{$this->loan->uuid}"]);

    $this->travelTo(now()->addDays(13));
    $remind();
    expect($count())->toBe(1);

    $this->travelTo(now()->addDay());
    $remind();
    expect($count())->toBe(2);

    ($this->other)(['amount' => '500', 'note' => 'x']);
    $this->travelTo(now()->addDays(14));
    $remind();
    expect($count())->toBe(2);
});
