<?php

// USSD part B: an agent records a loan or a repayment for one of their farmers. It waits as a pending entry until the farmer
// accepts it themselves, under "Waiting for you". No code is involved.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Contracts\SmsProvider;
use App\Models\Loan;
use App\Models\PendingEntry;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    buildLoanWorld($this);
    config(['services.ussd.secret' => 'test-secret']);

    $this->agent->forceFill(['phone' => '0201112233', 'phone_verified_at' => now(), 'ussd_pin_hash' => Hash::make('4829'), 'first_name' => 'Ama'])->save();
    $this->farmerUser->forceFill(['phone' => '0244445566', 'phone_verified_at' => now(), 'first_name' => 'Kofi'])->save();

    $this->dial = fn(string $input = '', bool $new = false) => $this->postJson('/ussd/callback/test-secret', [
        'sessionID' => 'a-1', 'msisdn' => '233201112233', 'newSession' => $new, 'userData' => $input,
    ]);
    // signed in as the agent, with the farmer picked, on the Loans and repayments menu
    $this->walk = function (string ...$inputs) {
        ($this->dial)('', true);
        ($this->dial)('4829');
        ($this->dial)('1');
        ($this->dial)('0244445566');
        $reply = ($this->dial)('6');

        foreach ($inputs as $input) {
            $reply = ($this->dial)($input);
        }

        return $reply;
    };
    $this->entries = fn() => PendingEntry::where('farmer_profile_id', $this->profile->id)->get();
});

test('the farmer menu has Loans and repayments, with the four choices', function () {
    $menu = ($this->walk)();

    expect($menu->json('message'))->toContain('1. New loan')->toContain('2. Existing loan')->toContain('3. Repayment')->toContain('4. Pending entries')->toContain('0. Back');
});

test('a new loan: lender, amount, paid into, no interest, no due date, confirm, and it waits for the farmer', function () {
    $ask = ($this->walk)('1');
    expect($ask->json('message'))->toContain('lender');

    $found = ($this->dial)('sika');
    expect($found->json('message'))->toContain('1. Sika Microfinance');

    expect(($this->dial)('1')->json('message'))->toContain('Enter the amount');
    expect(($this->dial)('500')->json('message'))->toContain('1. Cash')->toContain('2. MoMo')->toContain('3. Bank');
    expect(($this->dial)('1')->json('message'))->toContain('1. No interest');
    expect(($this->dial)('1')->json('message'))->toContain('1. No due date');

    $confirm = ($this->dial)('1');
    expect($confirm->json('message'))->toContain('Sika Microfinance')->toContain('GHS 500.00')->toContain('1. Yes');

    $done = ($this->dial)('1');
    $entry = ($this->entries)()->firstOrFail();
    $sms = collect(app(SmsProvider::class)->sent)->last();

    expect($done->json('continueSession'))->toBeFalse()->and($done->json('message'))->toContain('accept')
        ->and([$entry->status, $entry->entry_type, $entry->recorded_by, $entry->payload['principal_minor']])->toBe(['pending', 'loan.money', $this->agent->id, 50000])
        ->and(Loan::where('farmer_profile_id', $this->profile->id)->count())->toBe(0)
        ->and($sms['phone'])->toBe('0244445566')->and($sms['message'])->toContain('Waiting for you');
});

test('interest and a due date can be added, the date typed as DD/MM/YYYY', function () {
    $interest = ($this->walk)('1', 'sika', '1', '500', '2');
    expect($interest->json('message'))->toContain('1. No interest')->toContain('2.');

    expect(($this->dial)('2')->json('message'))->toContain('Enter the interest');
    expect(($this->dial)('50')->json('message'))->toContain('1. No due date');
    expect(($this->dial)('2')->json('message'))->toContain('DD/MM/YYYY');
    expect(($this->dial)('31/12/2099')->json('message'))->toContain('1. Yes');
    ($this->dial)('1');

    $payload = ($this->entries)()->firstOrFail()->payload;

    expect([$payload['agreed_interest_minor'], $payload['due_date'], $payload['settlement_account_id']])->toBe([5000, '2099-12-31', LedgerAccountId('Momo A/C')]);
});

test('a due date that is not a date, or is in the past, asks again', function () {
    ($this->walk)('1', 'sika', '1', '500', '1', '1', '2');

    foreach (['31-12-2099', '32/01/2099', 'soon', '01/01/2020'] as $bad) {
        $again = ($this->dial)($bad);

        expect($again->json('continueSession'))->toBeTrue()->and($again->json('message'))->toContain('DD/MM/YYYY');
    }

    expect(($this->entries)())->toHaveCount(0);
});

test('a lender that is not listed is explained, and one letter is not enough', function () {
    ($this->walk)('1');

    $none = ($this->dial)('zzzzzz');
    expect($none->json('continueSession'))->toBeTrue()->and($none->json('message'))->toContain('admin');

    $short = ($this->dial)('s');
    expect($short->json('message'))->toContain('lender');
});

test('an amount that is not above zero asks again', function () {
    ($this->walk)('1', 'sika', '1');

    foreach (['abc', '0.00', '1000000', ''] as $bad) {
        expect(($this->dial)($bad)->json('message'))->toContain('amount');
    }

    expect(($this->entries)())->toHaveCount(0);
});

test('an existing loan needs only the lender and the amount', function () {
    $confirm = ($this->walk)('2', 'sika', '1', '300');
    expect($confirm->json('message'))->toContain('Existing')->toContain('GHS 300.00');

    ($this->dial)('1');
    $entry = ($this->entries)()->firstOrFail();

    expect([$entry->entry_type, $entry->payload['principal_minor']])->toBe(['loan.existing', 30000]);
});

test('a repayment: pick the loan, the amount and where it is paid from, and it waits for the farmer', function () {
    $loan = ($this->openLoan)();

    $list = ($this->walk)('3');
    expect($list->json('message'))->toContain('1. Sika Microfinance')->toContain('GHS 1,200.00');

    expect(($this->dial)('1')->json('message'))->toContain('Enter the amount');
    expect(($this->dial)('100')->json('message'))->toContain('1. Cash');
    expect(($this->dial)('1')->json('message'))->toContain('GHS 100.00');

    $done = ($this->dial)('1');
    $entry = ($this->entries)()->firstOrFail();

    expect($done->json('continueSession'))->toBeFalse()
        ->and([$entry->status, $entry->entry_type, $entry->recorded_by])->toBe(['pending', 'loan.repayment', $this->agent->id])
        ->and($loan->repayments()->count())->toBe(0);
});

test('more than the loan still owes is refused with the reason', function () {
    ($this->openLoan)();
    ($this->walk)('3', '1', '5000', '1', '1');

    expect(($this->entries)())->toHaveCount(0);
});

test('with no loans to repay it says so', function () {
    $reply = ($this->walk)('3');

    expect($reply->json('continueSession'))->toBeFalse()->and($reply->json('message'))->toContain('no loans');
});

test('Pending entries lists what is waiting, and the agent can withdraw one', function () {
    ($this->walk)('1', 'sika', '1', '500', '1', '1', '1', '1');
    $entry = ($this->entries)()->firstOrFail();

    $list = ($this->walk)('4');
    expect($list->json('message'))->toContain('1.')->toContain('Sika Microfinance');

    $detail = ($this->dial)('1');
    expect($detail->json('message'))->toContain('GHS 500.00')->toContain('1. Withdraw');

    ($this->dial)('1');

    expect($entry->fresh()->status)->toBe('withdrawn');
});

test('with nothing pending it says so', function () {
    expect(($this->walk)('4')->json('message'))->toContain('Nothing');
});

test('0 goes back through the loan steps to the loans menu and the farmer menu', function () {
    ($this->walk)('1', 'sika', '1', '500');

    expect(($this->dial)('0')->json('message'))->toContain('Enter the amount')
        ->and(($this->dial)('0')->json('message'))->toContain('1. Sika Microfinance')
        ->and(($this->dial)('0')->json('message'))->toContain('lender')
        ->and(($this->dial)('0')->json('message'))->toContain('1. New loan')
        ->and(($this->dial)('0')->json('message'))->toContain('Farmer: Kofi');
});

test('the farmer\'s menu now shows Waiting for you without any switch', function () {
    $this->farmerUser->forceFill(['ussd_pin_hash' => Hash::make('4829')])->save();
    $farmer = fn(string $input, bool $new = false) => $this->postJson('/ussd/callback/test-secret', ['sessionID' => 'f-1', 'msisdn' => '233244445566', 'newSession' => $new, 'userData' => $input]);

    $farmer('', true);

    expect($farmer('4829')->json('message'))->toContain('8. Waiting for you');
});

function LedgerAccountId(string $name): int
{
    return \App\Models\LedgerAccount::where('name', $name)->value('id');
}
