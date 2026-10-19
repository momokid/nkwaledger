<?php

// USSD step 2: Farm sales under Log income. A sale takes a farm unit, a quantity, the amount, and Cash, MoMo, Bank or Credit.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\FarmType;
use App\Models\FarmUnit;
use App\Models\LedgerAccount;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    buildLoanWorld($this);
    config(['services.ussd.secret' => 'test-secret']);

    $this->farmerUser->forceFill(['phone' => '0244445566', 'phone_verified_at' => now(), 'ussd_pin_hash' => Hash::make('4829')])->save();
    $this->profile->farmTypes()->attach(FarmType::factory()->create()->id);

    $sales = LedgerAccount::where('name', 'Sales A/C')->firstOrFail();
    $this->produceSale = TransactionTemplate::create([
        'name' => 'I sold my farm produce', 'slug' => 'produce_sale', 'transaction_type' => 'INCOME', 'requires_farm_unit' => true,
        'is_produce_sale' => true, 'allows_credit' => true, 'debit_account_id' => $this->cash->id, 'credit_account_id' => $sales->id, 'settlement_side' => 'debit',
    ]);
    TransactionTemplate::create([
        'name' => 'Money came in from something else', 'slug' => 'other_income', 'transaction_type' => 'INCOME', 'requires_farm_unit' => false,
        'debit_account_id' => $this->cash->id, 'credit_account_id' => $sales->id, 'settlement_side' => 'debit',
    ]);

    $this->dial = fn(string $input = '', bool $new = false) => $this->postJson('/ussd/callback/test-secret', [
        'sessionID' => 's-1', 'msisdn' => '233244445566', 'newSession' => $new, 'userData' => $input,
    ]);
    $this->walk = function (string ...$inputs) {
        ($this->dial)('', true);
        $reply = ($this->dial)('4829');

        foreach ($inputs as $input) {
            $reply = ($this->dial)($input);
        }

        return $reply;
    };
    $this->sold = fn() => Transaction::where('channel', 'ussd')->get();
});

test('Log income asks Farm sales or Other money in first', function () {
    expect(($this->walk)('3')->json('message'))->toContain('1. Farm sales')->toContain('2. Other money in')->toContain('0. Back');
});

test('Farm sales lists the sales that fit this farmer', function () {
    expect(($this->walk)('3', '1')->json('message'))->toContain('1. I sold my farm produce');
});

test('with one approved unit it asks the quantity, the amount and how it was paid, then saves against that unit', function () {
    $quantity = ($this->walk)('3', '1', '1');
    expect($quantity->json('message'))->toContain('How much did you sell?');

    expect(($this->dial)('10')->json('message'))->toContain('Enter the amount');
    expect(($this->dial)('200')->json('message'))->toContain('1. Cash')->toContain('4. Credit');

    $confirm = ($this->dial)('1');
    expect($confirm->json('message'))->toContain('I sold my farm produce')->toContain('10')->toContain('GHS 200.00')->toContain('Cash');

    $done = ($this->dial)('1');
    $transaction = ($this->sold)()->firstOrFail();

    expect($done->json('continueSession'))->toBeFalse()
        ->and([$transaction->farm_unit_id, $transaction->amount_minor, (float) $transaction->quantity_sold, $transaction->is_credit])->toBe([$this->unit->id, 20000, 10.0, false]);
});

test('with two approved units it asks which unit', function () {
    $second = ($this->unitOf)($this->profile);

    $ask = ($this->walk)('3', '1', '1');
    expect($ask->json('message'))->toContain('Which farm unit?')->toContain('1.')->toContain('2.')->toContain('0. Back');

    ($this->dial)('2');
    ($this->dial)('5');
    ($this->dial)('50');
    ($this->dial)('2');
    ($this->dial)('1');

    // units are listed by name, so the second in the list is the second by name
    expect(($this->sold)()->firstOrFail()->farm_unit_id)->toBe(collect([$this->unit, $second])->sortBy('name')->values()[1]->id);
});

test('with no approved unit the farmer is told so and nothing is saved', function () {
    FarmUnit::whereKey($this->unit->id)->update(['approved_at' => null]);

    $reply = ($this->walk)('3', '1', '1');

    expect($reply->json('continueSession'))->toBeFalse()->and($reply->json('message'))->toContain('farm unit')
        ->and(($this->sold)())->toHaveCount(0);
});

test('Credit records the sale as money still owed to the farmer', function () {
    ($this->walk)('3', '1', '1', '4', '120', '4', '1');

    $transaction = ($this->sold)()->firstOrFail();

    expect($transaction->is_credit)->toBeTrue()->and($transaction->settlementAccount->name)->toBe('Accounts Receivable');
});

test('Credit is offered for sales only, never for other money in', function () {
    $other = ($this->walk)('3', '2', '1', '50');

    expect($other->json('message'))->not->toContain('Credit');
});

test('a quantity that is not above zero asks again', function () {
    ($this->walk)('3', '1', '1');

    foreach (['abc', '0.00', '12.345', '1000000', ''] as $bad) {
        $again = ($this->dial)($bad);

        expect($again->json('continueSession'))->toBeTrue()->and($again->json('message'))->toContain('How much');
    }
});

test('more than is in stock is refused and nothing is saved', function () {
    ($this->walk)('3', '1', '1', '9999', '500', '1', '1');

    expect(($this->sold)())->toHaveCount(0);
});

test('0 goes back through payment, amount and quantity to the sales list and the menu', function () {
    ($this->walk)('3', '1', '1', '5', '50');

    expect(($this->dial)('0')->json('message'))->toContain('Enter the amount')
        ->and(($this->dial)('0')->json('message'))->toContain('How much')
        ->and(($this->dial)('0')->json('message'))->toContain('1. I sold my farm produce')
        ->and(($this->dial)('0')->json('message'))->toContain('1. Farm sales')
        ->and(($this->dial)('0')->json('message'))->toContain('3. Log income');
});
