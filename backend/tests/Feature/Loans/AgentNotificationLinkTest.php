<?php

// A loan notification opens the agent's own Loans page and names the one thing to look at (?focus=<public id>).
// Nothing in it points at the farmer's pages or the admin's, and no row id is in it.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\Notification;
use App\Models\PendingEntry;
use App\Services\Ledger\ReversalService;

beforeEach(function () {
    buildLoanWorld($this);

    $this->toAgent = fn(string $kind) => Notification::where('user_id', $this->agent->id)->where('kind', $kind)->latest('id')->first();
});

test('a lender check opens the loan and points at the newest check', function () {
    $loan = ($this->openLoan)();
    $this->actingAs($this->admin)->postJson("/admin/loans/{$loan->uuid}/checks", ['status' => 'confirmed', 'note' => 'Spoke to the manager'])->assertStatus(201);

    $check = $loan->checks()->latest('id')->first();

    expect(($this->toAgent)('loan.checked')->link)->toBe("/agent/loans/{$loan->uuid}?focus={$check->uuid}");
});

test('the farmer\'s own notice still opens the farmer\'s own loan, with nothing to focus on', function () {
    $loan = ($this->openLoan)();
    $this->actingAs($this->admin)->postJson("/admin/loans/{$loan->uuid}/checks", ['status' => 'confirmed'])->assertStatus(201);

    expect(Notification::where('user_id', $this->farmerUser->id)->where('kind', 'loan.checked')->first()->link)->toBe("/loans/{$loan->uuid}");
});

test('an accepted loan opens that loan and points at it', function () {
    $entry = ($this->pendingMoney)();
    $loan = $this->entries->accept($entry, $this->farmerUser, 'app');

    expect(($this->toAgent)('loan.accepted')->link)->toBe("/agent/loans/{$loan->uuid}?focus={$loan->uuid}");
});

test('an accepted repayment opens its loan and points at the repayment', function () {
    $loan = ($this->openLoan)();
    $entry = $this->entries->recordRepayment($loan, $this->agent, ($this->repayForm)('100'));
    $repayment = $this->entries->accept($entry, $this->farmerUser, 'app');

    expect(($this->toAgent)('loan.accepted')->link)->toBe("/agent/loans/{$loan->uuid}?focus={$repayment->uuid}");
});

test('a repayment rejected because the loan was cancelled opens that loan and points at the rejected entry', function () {
    $loan = ($this->openLoan)();
    $entry = $this->entries->recordRepayment($loan, $this->agent, ($this->repayForm)('100'));
    $this->entries->rejectRepaymentsFor($loan, $this->agent);

    expect(($this->toAgent)('loan.rejected')->link)->toBe("/agent/loans/{$loan->uuid}?focus={$entry->uuid}");
});

test('a repayment rejected because the loan changed opens that loan and points at the rejected entry', function () {
    $loan = ($this->openLoan)();
    $entry = $this->entries->recordRepayment($loan, $this->agent, ($this->repayForm)('100'));
    ($this->repay)($loan, '50');

    expect($this->entries->accept($entry, $this->farmerUser, 'app'))->toBeNull()
        ->and(($this->toAgent)('loan.rejected')->link)->toBe("/agent/loans/{$loan->uuid}?focus={$entry->uuid}");
});

test('a declined loan has no loan to open, so it keeps the agent\'s page for that farmer', function () {
    $entry = ($this->pendingMoney)();
    $this->entries->decline($entry, $this->farmerUser);

    expect(($this->toAgent)('loan.declined')->link)->toBe("/agent/farmers/{$this->profile->uuid}");
});

test('the focus names something on the page: a check, a repayment or a rejected entry the agent can open', function () {
    $loan = ($this->openLoan)();
    $this->actingAs($this->admin)->postJson("/admin/loans/{$loan->uuid}/checks", ['status' => 'confirmed'])->assertStatus(201);
    $entry = $this->entries->recordRepayment($loan, $this->agent, ($this->repayForm)('100'));
    $this->entries->rejectRepaymentsFor($loan, $this->agent);

    $props = $this->actingAs($this->agent)->get("/agent/loans/{$loan->uuid}")->viewData('page')['props'];

    expect(collect($props['checks'])->pluck('uuid')->all())->toContain($loan->checks()->first()->uuid)
        ->and(collect($props['rejected'])->pluck('uuid')->all())->toContain($entry->uuid);
});
