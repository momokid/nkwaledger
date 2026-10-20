<?php

// Saving a repayment from the farmer's loan page happens in place: the answer carries the loan's new figures, the
// repayments and a fresh key, so the page can clear the form and show the new line without reloading. A repeat of
// the same key is still one repayment; a new key is a new repayment.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\LoanRepayment;

beforeEach(function () {
    buildLoanWorld($this);
    $this->loan = ($this->openLoan)();
    $this->save = fn(string $amount, array $over = [], $as = null) => ($as ?? $this->actingAs($this->farmerUser))
        ->postJson("/loans/{$this->loan->uuid}/repayments", ($this->repayForm)($amount, array_merge(['paid_from' => 'cash'], $over)));
});

test('a saved repayment answers with the new figures, the repayments and a fresh key', function () {
    $response = ($this->save)('600', ['idempotency_key' => 'first-key'])->assertStatus(201);
    $body = $response->json();

    expect($body['loan']['balance'])->toBe(60000)
        ->and($body['loan']['uuid'])->toBe($this->loan->uuid)
        ->and($body['repayments'])->toHaveCount(1)
        ->and($body['repayments'][0]['amount'])->toBe(60000)
        ->and($body['rejected'])->toBe([])
        ->and($body['idempotencyKey'])->not->toBe('first-key')->and(\Illuminate\Support\Str::isUuid($body['idempotencyKey']))->toBeTrue()
        ->and(LoanRepayment::count())->toBe(1);
});

test('a second repayment with the fresh key is recorded as its own repayment', function () {
    $fresh = ($this->save)('300')->json('idempotencyKey');
    $second = ($this->save)('200', ['idempotency_key' => $fresh])->assertStatus(201)->json();

    expect(LoanRepayment::count())->toBe(2)
        ->and($second['repayments'])->toHaveCount(2)
        ->and($second['loan']['balance'])->toBe(120000 - 50000);
});

test('sending the same key again does not record the repayment twice', function () {
    ($this->save)('300', ['idempotency_key' => 'same'])->assertStatus(201);
    $again = ($this->save)('300', ['idempotency_key' => 'same'])->assertStatus(201)->json();

    expect(LoanRepayment::count())->toBe(1)->and($again['repayments'])->toHaveCount(1);
});

test('a refused repayment says why, records nothing and keeps the page as it is', function () {
    $response = ($this->save)('5000')->assertStatus(422);

    expect($response->json('message'))->toBe('The amount is more than the loan still owes')
        ->and(LoanRepayment::count())->toBe(0);
});

test('a bad amount or date is refused with the usual field errors', function () {
    ($this->save)('abc')->assertStatus(422)->assertJsonValidationErrors('amount');
    ($this->save)('10', ['date' => now()->addDay()->toDateString()])->assertStatus(422)->assertJsonValidationErrors('date');

    expect(LoanRepayment::count())->toBe(0);
});

test('nobody but the farmer the loan belongs to can save here', function (string $who) {
    $user = ['agent' => $this->agent, 'admin' => $this->admin, 'otherFarmer' => $this->otherFarmerUser, 'vet' => $this->vet][$who];

    ($this->save)('10', [], $this->actingAs($user))->assertNotFound();

    expect(LoanRepayment::count())->toBe(0);
})->with(['agent', 'admin', 'otherFarmer', 'vet']);

test('the answer carries public ids only', function () {
    $json = json_encode(($this->save)('600')->json());

    expect($json)->not->toContain('"id"')->not->toContain('farmer_profile_id')->not->toContain('"loan_id"');
});

test('a plain form post still goes back to the loan page', function () {
    $this->actingAs($this->farmerUser)->post("/loans/{$this->loan->uuid}/repayments", ($this->repayForm)('100', ['paid_from' => 'cash']))
        ->assertRedirect("/loans/{$this->loan->uuid}");
});
