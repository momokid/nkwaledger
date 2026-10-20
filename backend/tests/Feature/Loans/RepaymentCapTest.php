<?php

// A loan takes back what it is owed and not a pesewa more. Once the principal and the interest are both paid it is
// fully repaid and takes no more entries. This holds for the farmer, for an agent's entry, and when it is accepted.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Exceptions\Ledger\PostingFailed;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\PendingEntry;
use App\Support\Wording;

beforeEach(function () {
    buildLoanWorld($this);

    // principal 100000, interest 20000: 120000 owed in all
    $this->loan = ($this->openLoan)();
    $this->over = Wording::get('repayment.over_total');
    $this->repaid = Wording::get('repayment.fully_repaid');
    $this->httpForm = fn(string $amount) => ['amount' => $amount, 'date' => now()->toDateString(), 'paid_from' => 'cash', 'idempotency_key' => 'k-' . uniqid()];
});

test('the words are the approved ones', function () {
    expect($this->over)->toBe('The amount is more than the loan still owes')
        ->and($this->repaid)->toBe('This loan is fully repaid');
});

test('an amount above what the loan owes is refused and nothing is posted', function () {
    expect(fn() => ($this->repay)($this->loan, '1200.01'))->toThrow(PostingFailed::class, $this->over)
        ->and(LoanRepayment::count())->toBe(0)
        ->and(Loan::find($this->loan->id)->balanceMinor())->toBe(120000);
});

test('exactly what the loan owes is accepted, and the loan is then fully repaid', function () {
    $repayment = ($this->repay)($this->loan, '1200');

    expect([$repayment->principal_minor, $repayment->interest_minor])->toBe([100000, 20000])
        ->and(Loan::find($this->loan->id)->balanceMinor())->toBe(0)
        ->and(Loan::find($this->loan->id)->displayStatus())->toBe('repaid');
});

test('after part payments the next one cannot be more than what is left', function () {
    ($this->repay)($this->loan, '600');

    expect(fn() => ($this->repay)($this->loan, '600.01'))->toThrow(PostingFailed::class, $this->over);

    ($this->repay)($this->loan, '600');

    expect(Loan::find($this->loan->id)->balanceMinor())->toBe(0);
});

test('a fully repaid loan takes no more repayments, whatever the amount', function () {
    ($this->repay)($this->loan, '1200');

    expect(fn() => ($this->repay)($this->loan, '0.01'))->toThrow(PostingFailed::class, $this->repaid)
        ->and(fn() => ($this->repay)($this->loan, '500'))->toThrow(PostingFailed::class, $this->repaid)
        ->and(LoanRepayment::count())->toBe(1);
});

test('an agent cannot record an entry above what the loan owes, or any entry on a fully repaid loan', function () {
    expect(fn() => $this->entries->recordRepayment($this->loan, $this->agent, ($this->repayForm)('1300')))->toThrow(PostingFailed::class, $this->over)
        ->and(PendingEntry::count())->toBe(0);

    ($this->repay)($this->loan, '1200');

    expect(fn() => $this->entries->recordRepayment($this->loan, $this->agent, ($this->repayForm)('10')))->toThrow(PostingFailed::class, $this->repaid)
        ->and(PendingEntry::count())->toBe(0);
});

test('an entry that fitted when the agent typed it is checked again when the farmer accepts it', function () {
    $entry = $this->entries->recordRepayment($this->loan, $this->agent, ($this->repayForm)('1200'));
    ($this->repay)($this->loan, '600');

    $result = $this->entries->accept($entry, $this->farmerUser, 'app');

    expect($result)->toBeNull()->and($entry->fresh()->status)->toBe('rejected')->and(LoanRepayment::count())->toBe(1);
});

test('over HTTP the farmer is told the amount is too much and nothing is saved', function () {
    $this->actingAs($this->farmerUser)->post("/loans/{$this->loan->uuid}/repayments", ($this->httpForm)('1300'))
        ->assertSessionHas('error', $this->over);

    expect(LoanRepayment::count())->toBe(0);
});

test('the preview says the same, so the form can show it before anything is saved', function () {
    $this->actingAs($this->farmerUser)->postJson("/loans/{$this->loan->uuid}/repayments/preview", ['amount' => '1300'])
        ->assertStatus(422)->assertJson(['message' => $this->over]);

    $this->actingAs($this->farmerUser)->postJson("/loans/{$this->loan->uuid}/repayments/preview", ['amount' => '1200'])
        ->assertOk()->assertJson(['principal' => 100000, 'interest' => 20000]);

    ($this->repay)($this->loan, '1200');

    $this->actingAs($this->farmerUser)->postJson("/loans/{$this->loan->uuid}/repayments/preview", ['amount' => '10'])
        ->assertStatus(422)->assertJson(['message' => $this->repaid]);
});

test('the SMS code route never has an extra amount to refuse any more: an entry can only be what is owed', function () {
    $entry = $this->entries->recordRepayment($this->loan, $this->agent, ($this->repayForm)('1200'));

    $this->codes->send($entry, $this->agent);
    preg_match('/Code: (\d{6})$/', end(app(\App\Contracts\SmsProvider::class)->sent)['message'], $match);

    $repayment = $this->codes->enter($entry, $this->agent, $match[1]);

    expect($repayment)->toBeInstanceOf(LoanRepayment::class)->and(Loan::find($this->loan->id)->displayStatus())->toBe('repaid');
});

test('the loan page tells the page a fully repaid loan is repaid, so it can drop the repayment button', function () {
    ($this->repay)($this->loan, '1200');

    $props = $this->actingAs($this->farmerUser)->get("/loans/{$this->loan->uuid}")->viewData('page')['props'];

    expect($props['loan']['status'])->toBe('repaid');
});
