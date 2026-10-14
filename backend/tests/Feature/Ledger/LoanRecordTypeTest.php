<?php

// the fifth record type, LOAN: posted like any record and cancellable, but kept out of income, expense and
// loss, net, the dashboards, My Farm, admin and agent figures and every activity count - exactly as if it
// did not exist. No loan template is seeded; the ones here are test-only.

require_once __DIR__ . '/../../Support/LockedFiguresFixture.php';
require_once __DIR__ . '/../../Support/FigureSnapshot.php';

use App\Models\FarmerProfile;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Services\Ledger\Reports\TrialBalanceService;

beforeEach(function () {
    buildLockedFigures($this);
    wireFiguresToAnAgent($this);
    addLoanTestTemplates($this);

    // a farmer with loan records and nothing else, in the same community and under the same agent: if any
    // activity count or roster forgot the new type, this farmer would suddenly count as active
    $this->loanOnly = FarmerProfile::factory()->create([
        'assigned_agent_id' => $this->agent->id,
        'community_id' => $this->provisional->community_id,
    ]);
    $this->loanOnlyUnit = ($this->unitOf)($this->loanOnly);

    $this->addLoanRecords = function () {
        $received = ($this->put)($this->profile, $this->unit, $this->loanReceivedTemplate, '500');
        ($this->put)($this->profile, $this->unit, $this->loanRepaidTemplate, '200');
        ($this->put)($this->loanOnly, $this->loanOnlyUnit, $this->loanReceivedTemplate, '800');
        ($this->put)($this->provisional, $this->provisionalUnit, $this->loanReceivedTemplate, '300');

        return $received;
    };
});

test('the new type is called LOAN and is not one of the figure types', function () {
    expect(Transaction::LOAN)->toBe('LOAN')
        ->and(Transaction::TYPES)->toContain('LOAN')
        ->and(Transaction::FIGURE_TYPES)->toBe([Transaction::INCOME, Transaction::EXPENSE, Transaction::LOSS]);
});

test('a loan record posts through the posting service and the books stay balanced', function () {
    $loan = ($this->put)($this->profile, $this->unit, $this->loanReceivedTemplate, '500');

    expect([$loan->transaction_type, $loan->amount_minor])->toBe(['LOAN', 50000])
        ->and(app(TrialBalanceService::class)->for($this->profile->id, $this->from, $this->to)->isBalanced())->toBeTrue();
});

test('the control: two snapshots with nothing added are identical', function () {
    expect(figureSnapshot($this))->toBe(figureSnapshot($this));
});

test('loan records change none of the income, expense, loss, net, cash, dashboard, My Farm, admin or agent figures', function () {
    $before = figureSnapshot($this);

    ($this->addLoanRecords)();

    expect(figureSnapshot($this))->toBe($before);
});

test('a cancelled loan record counts as zero, and cancelling goes through the normal request and approval', function () {
    $before = figureSnapshot($this);

    $loan = ($this->addLoanRecords)();
    ($this->cancel)($loan);

    expect($loan->fresh()->reversedBy)->not->toBeNull()
        ->and(figureSnapshot($this))->toBe($before)
        ->and(app(TrialBalanceService::class)->for($this->profile->id, $this->from, $this->to)->isBalanced())->toBeTrue();
});

test('a farmer cannot pick a loan template, on the form list or when saving', function () {
    $refusal = TransactionTemplate::refusalFor($this->loanReceivedTemplate->id, $this->profile);

    expect($refusal)->toBe(TransactionTemplate::NOT_FOR_FARM);

    $this->actingAs($this->farmerUser)->post('/my-records', [
        'transaction_template_id' => $this->loanReceivedTemplate->id,
        'amount' => '100',
        'settlement_account_id' => $this->cash->id,
        'farm_unit_id' => $this->unit->id,
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasErrors('transaction_template_id');

    $listed = null;
    $this->actingAs($this->farmerUser)->get('/my-records/create')->assertOk()->assertInertia(function ($page) use (&$listed) {
        $listed = collect($page->toArray()['props']['templates'])->pluck('id')->all();
    });

    expect($listed)->not->toContain($this->loanReceivedTemplate->id)->not->toContain($this->loanRepaidTemplate->id);
});

test('the admin template form still offers only the four old types', function () {
    expect(TransactionTemplate::TYPES)->toBe(['INCOME', 'EXPENSE', 'LOSS', 'ADJUSTMENT']);
});
