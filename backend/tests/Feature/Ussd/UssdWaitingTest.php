<?php

// USSD slice 4: a farmer sees the loans and repayments their agent recorded, and accepts or declines them. No code needed.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\Notification;
use App\Models\PendingEntry;
use App\Services\Loans\PendingEntryService;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    buildLoanWorld($this);
    config(['services.ussd.secret' => 'test-secret']);

    $this->farmerUser->forceFill(['phone' => '0244445566', 'phone_verified_at' => now(), 'ussd_pin_hash' => Hash::make('4829')])->save();

    $this->entries = app(PendingEntryService::class);
    $this->waitingLoan = fn(string $lender = 'Sika Microfinance', string $amount = '500') => $this->entries->recordLoan($this->profile, $this->agent, ($this->moneyEntry)(['lender_name' => $lender, 'amount' => $amount]));

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
    $this->told = fn(string $kind) => Notification::where('user_id', $this->agent->id)->where('kind', $kind)->count();
});

test('the menu offers Waiting for you', function () {
    ($this->dial)('', true);

    expect(($this->dial)('4829')->json('message'))->toContain('7. Waiting for you');
});

test('with nothing waiting it says so and ends', function () {
    $reply = ($this->walk)('7');

    expect($reply->json('continueSession'))->toBeFalse()->and($reply->json('message'))->toContain('Nothing is waiting');
});

test('a waiting loan is listed and its details carry the date as DD/MM/YYYY and the not-counted notice', function () {
    ($this->waitingLoan)();

    $list = ($this->walk)('7');
    expect($list->json('continueSession'))->toBeTrue()->and($list->json('message'))->toContain('1.')->toContain('Sika Microfinanc')->toContain('GHS 500.00');

    $detail = ($this->dial)('1');

    expect($detail->json('message'))->toContain('Sika Microfinance')->toContain('GHS 500.00')->toContain(now()->format('d/m/Y'))
        ->toContain('not counted until you accept it')->toContain('1. Accept')->toContain('2. Decline');
});

test('accepting makes the loan, marks it as accepted by USSD and tells the agent', function () {
    $entry = ($this->waitingLoan)();

    $done = ($this->walk)('7', '1', '1');
    $entry->refresh();

    expect($done->json('continueSession'))->toBeFalse()
        ->and($entry->status)->toBe(PendingEntry::ACCEPTED)
        ->and($entry->decided_via)->toBe('ussd')
        ->and(Loan::where('farmer_profile_id', $this->profile->id)->where('accepted_via', 'ussd')->firstOrFail()->accepted_via)->toBe('ussd')
        ->and(($this->told)('loan.accepted'))->toBe(1);
});

test('declining makes no loan and tells the agent', function () {
    $entry = ($this->waitingLoan)();

    $done = ($this->walk)('7', '1', '2');
    $entry->refresh();

    expect($done->json('continueSession'))->toBeFalse()
        ->and([$entry->status, $entry->decided_via])->toBe([PendingEntry::DECLINED, 'ussd'])
        ->and(Loan::where('farmer_profile_id', $this->profile->id)->where('accepted_via', 'ussd')->exists())->toBeFalse()
        ->and(($this->told)('loan.declined'))->toBe(1);
});

test('a waiting repayment is listed and accepted the same way', function () {
    $loan = ($this->openLoan)();
    $entry = $this->entries->recordRepayment($loan, $this->agent, ($this->repayForm)('100'));

    $list = ($this->walk)('7');
    expect($list->json('message'))->toContain('Repayment');

    ($this->dial)('1');
    ($this->dial)('1');

    expect($entry->fresh()->status)->toBe(PendingEntry::ACCEPTED)
        ->and(LoanRepayment::where('loan_id', $loan->id)->firstOrFail()->accepted_via)->toBe('ussd');
});

test('another farmer\'s waiting entry is never shown', function () {
    $this->entries->recordLoan($this->clean, $this->agent, ($this->moneyEntry)(['lender_name' => 'Not Yours']));

    $reply = ($this->walk)('7');

    expect($reply->json('message'))->not->toContain('Not Yours')->toContain('Nothing is waiting');
});

test('an answer that is not on the list shows the list again', function () {
    ($this->waitingLoan)();
    ($this->walk)('7');

    $again = ($this->dial)('9');

    expect($again->json('continueSession'))->toBeTrue()->and($again->json('message'))->toContain('Sika Microfinanc');
});
