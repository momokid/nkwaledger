<?php

// USSD slice 3: a farmer logs money in or money out from the menu. Cash, MoMo or Bank. The same posting path as the web.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\FarmType;
use App\Models\LedgerAccount;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    buildLoanWorld($this);
    config(['services.ussd.secret' => 'test-secret']);

    $this->farmerUser->forceFill(['phone' => '0244445566', 'phone_verified_at' => now(), 'ussd_pin_hash' => Hash::make('4829')])->save();
    $this->profile->farmTypes()->attach(FarmType::factory()->create()->id);

    $account = fn(string $name) => LedgerAccount::where('name', $name)->firstOrFail();
    $make = fn(string $name, string $slug, string $kind, string $debit, string $credit, string $side) => TransactionTemplate::create([
        'name' => $name, 'slug' => $slug, 'transaction_type' => $kind, 'requires_farm_unit' => false,
        'debit_account_id' => $account($debit)->id, 'credit_account_id' => $account($credit)->id, 'settlement_side' => $side,
    ]);

    $make('Money came in from something else', 'other_income', 'INCOME', 'Cash A/C', 'Sales A/C', 'debit');
    $make('I paid someone to work', 'labour_cost', 'EXPENSE', 'Feed A/C', 'Cash A/C', 'credit');
    $make('I paid for transport', 'transport_cost', 'EXPENSE', 'Feed A/C', 'Cash A/C', 'credit');

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
});

test('the menu offers Log income and Log expenditure', function () {
    ($this->dial)('', true);

    expect(($this->dial)('4829')->json('message'))->toContain('3. Log income')->toContain('4. Log expenditure');
});

test('expenditure: transport, 50, MoMo, confirm, saved on the ussd channel from the MoMo account', function () {
    $pick = ($this->walk)('4');
    expect($pick->json('message'))->toContain('1. I paid someone to work')->toContain('2. I paid for transport');

    ($this->dial)('2');
    ($this->dial)('50');
    $confirm = ($this->dial)('2');

    expect($confirm->json('message'))->toContain('I paid for transport')->toContain('GHS 50.00')->toContain('MoMo')
        ->and(Transaction::where('channel', 'ussd')->count())->toBe(0);

    $saved = ($this->dial)('1');
    $transaction = Transaction::where('channel', 'ussd')->firstOrFail();

    expect($saved->json('continueSession'))->toBeFalse()
        ->and($saved->json('message'))->toContain($transaction->reference)
        ->and([$transaction->channel, $transaction->amount_minor, $transaction->recorded_by, $transaction->farmer_profile_id])
        ->toBe(['ussd', 5000, $this->farmerUser->id, $this->profile->id])
        ->and($transaction->settlementAccount->name)->toBe('Momo A/C')
        ->and($transaction->idempotency_key)->not->toBeNull();
});

test('income: 120.50 received in the bank', function () {
    ($this->walk)('3', '1', '120.50', '3', '1');

    $transaction = Transaction::where('channel', 'ussd')->firstOrFail();

    expect([$transaction->channel, $transaction->amount_minor, $transaction->settlementAccount->name])->toBe(['ussd', 12050, 'Bank A/C']);
});

test('saying no at the confirm screen saves nothing', function () {
    $last = ($this->walk)('4', '1', '30', '1', '2');

    expect($last->json('continueSession'))->toBeFalse()
        ->and(Transaction::where('channel', 'ussd')->count())->toBe(0);
});

test('an amount that is not above zero, is not a number, or is too large asks again and saves nothing', function () {
    ($this->walk)('4', '1');

    foreach (['0.00', 'abc', '12.345', '100001', ''] as $bad) {
        $again = ($this->dial)($bad);

        expect($again->json('continueSession'))->toBeTrue()
            ->and($again->json('message'))->toContain('amount');
    }

    expect(Transaction::where('channel', 'ussd')->count())->toBe(0);
});

test('a farmer whose profile is on hold is told why and nothing is saved', function () {
    $this->profile->update(['is_active' => false]);

    $reply = ($this->walk)('3');

    expect($reply->json('continueSession'))->toBeFalse()
        ->and($reply->json('message'))->toContain('on hold')
        ->and(Transaction::where('channel', 'ussd')->count())->toBe(0);
});

test('back from each logging step', function () {
    $screens = [];
    ($this->walk)('4');
    $screens['pick'] = ($this->dial)('2')->json('message');
    $screens['amount'] = ($this->dial)('50')->json('message');
    $screens['method'] = ($this->dial)('2')->json('message');
    $screens['confirm'] = $screens['method'];

    foreach (['amount', 'method'] as $step) {
        expect($screens[$step])->toContain('0. Back');
    }

    expect(($this->dial)('0')->json('message'))->toContain('Paid from?')
        ->and(($this->dial)('0')->json('message'))->toContain('Enter the amount')
        ->and(($this->dial)('0')->json('message'))->toContain('Money out.')->toContain('I paid for transport')
        ->and(($this->dial)('0')->json('message'))->toContain('4. Log expenditure')
        ->and(Transaction::where('channel', 'ussd')->count())->toBe(0);
});
