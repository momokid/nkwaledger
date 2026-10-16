<?php

// The admin checks a loan with the lender and records the result with a note. Each check is written once and never
// changed; the newest one is the loan's current status. The farmer and the farmer's agent read the result and the
// note on their own screens, and are told when it is saved.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanCheck;
use App\Models\Notification;
use App\Support\Wording;

beforeEach(function () {
    buildLoanWorld($this);

    $this->loan = ($this->openLoan)(['lender_name' => 'Sika Microfinance']);
    $this->check = fn(string $status = 'confirmed', ?string $note = null, $who = null, array $extra = []) => ($this->actingAs($who ?? $this->admin))
        ->postJson("/admin/loans/{$this->loan->uuid}/checks", array_filter(['status' => $status, 'note' => $note], fn($value) => $value !== null) + $extra);
    $this->farmerName = trim("{$this->farmerUser->surname} {$this->farmerUser->first_name}");
});

test('the words are the approved ones', function () {
    expect(Wording::get('lender.check_heading'))->toBe('Confirm with the lender')
        ->and(Wording::get('lender.check_option_not_confirmed'))->toBe('Not Confirmed')
        ->and(Wording::get('lender.check_option_unreachable'))->toBe('Lender could not be reached')
        ->and(Wording::get('lender.check_line_confirmed', ['date' => '2026-10-16']))->toBe('Confirmed with the lender on 2026-10-16');
});

test('only an admin may record a check', function (string $who, int $status) {
    $user = ['admin' => $this->admin, 'farmer' => $this->farmerUser, 'agent' => $this->agent, 'vet' => $this->vet, 'adviser' => $this->adviser][$who];

    ($this->check)('confirmed', 'Phoned the branch', $user)->assertStatus($status);

    expect(LoanCheck::count())->toBe($status === 201 ? 1 : 0);
})->with([
    'admin' => ['admin', 201],
    'farmer' => ['farmer', 403],
    'agent' => ['agent', 403],
    'vet' => ['vet', 403],
    'adviser' => ['adviser', 403],
]);

test('the status is always required and must be one of the four; the note is optional and at most 500 characters', function () {
    ($this->check)('')->assertStatus(422)->assertJsonValidationErrors(['status']);
    ($this->check)('maybe')->assertStatus(422)->assertJsonValidationErrors(['status']);
    ($this->check)('confirmed', str_repeat('a', 501))->assertStatus(422)->assertJsonValidationErrors(['note']);

    expect(LoanCheck::count())->toBe(0);

    foreach (['confirmed', 'not_confirmed', 'does_not_match', 'unreachable'] as $status) {
        ($this->check)($status)->assertStatus(201);
    }

    ($this->check)('confirmed', str_repeat('a', 500))->assertStatus(201);

    expect(LoanCheck::count())->toBe(5);
});

test('a check is saved with who made it, is audit-logged, and can never be changed or deleted', function () {
    $response = ($this->check)('does_not_match', 'The branch has it at 800, not 1000')->assertStatus(201);

    $check = LoanCheck::first();

    expect([$check->status, $check->note, (int) $check->recorded_by])->toBe(['does_not_match', 'The branch has it at 800, not 1000', $this->admin->id])
        ->and($response->json('check.status'))->toBe('does_not_match')
        ->and($response->json('check.note'))->toBe('The branch has it at 800, not 1000');

    $log = AuditLog::where('auditable_type', LoanCheck::class)->where('auditable_id', $check->id)->first();

    expect($log->action)->toBe('loan_check.created')->and((int) $log->user_id)->toBe($this->admin->id)
        ->and(fn() => $check->update(['note' => 'changed']))->toThrow(RuntimeException::class)
        ->and(fn() => $check->delete())->toThrow(RuntimeException::class);
});

test('the newest check is the current one and the older ones stay in the history', function () {
    ($this->check)('unreachable', 'Phone off')->assertStatus(201);
    $second = ($this->check)('confirmed', 'Spoke to the manager')->assertStatus(201);

    expect($second->json('check.status'))->toBe('confirmed')
        ->and(collect($second->json('checks'))->pluck('status')->all())->toBe(['confirmed', 'unreachable'])
        ->and(collect($second->json('checks'))->pluck('note')->all())->toBe(['Spoke to the manager', 'Phone off'])
        ->and($second->json('checks.0.by'))->toBe(trim("{$this->admin->surname} {$this->admin->first_name}"));
});

test('sending the same save twice makes one check', function () {
    ($this->check)('confirmed', 'Called', null, ['idempotency_key' => 'k-1'])->assertStatus(201);
    ($this->check)('confirmed', 'Called', null, ['idempotency_key' => 'k-1'])->assertStatus(201);

    expect(LoanCheck::count())->toBe(1);
});

test('a cancelled loan cannot be checked', function () {
    Loan::whereKey($this->loan->id)->update(['status' => Loan::CANCELLED]);

    ($this->check)('confirmed')->assertStatus(422);

    expect(LoanCheck::count())->toBe(0);
});

test('the farmer and the farmer\'s agent are told, each on their own screen, and the admin is not', function (string $status, string $text) {
    ($this->check)($status, 'Spoke to the manager');

    $toFarmer = Notification::where('user_id', $this->farmerUser->id)->where('kind', 'loan.checked')->get();
    $toAgent = Notification::where('user_id', $this->agent->id)->where('kind', 'loan.checked')->get();

    expect($toFarmer)->toHaveCount(1)->and($toAgent)->toHaveCount(1)
        ->and($toFarmer[0]->message)->toBe($text)->and($toAgent[0]->message)->toBe($text)
        ->and($toFarmer[0]->link)->toBe("/loans/{$this->loan->uuid}")
        ->and($toAgent[0]->link)->toBe("/agent/farmers/{$this->profile->uuid}")
        ->and(Notification::where('user_id', $this->admin->id)->where('kind', 'loan.checked')->count())->toBe(0);
})->with([
    'confirmed' => ['confirmed', 'Sika Microfinance: confirmed with the lender'],
    'not confirmed' => ['not_confirmed', 'Sika Microfinance: Not Confirmed'],
    'does not match' => ['does_not_match', 'Sika Microfinance: the lender says this does not match'],
    'unreachable' => ['unreachable', 'Sika Microfinance: Lender could not be reached'],
]);

test('a farmer with no agent is told alone', function () {
    $this->clean->update(['assigned_agent_id' => null]);
    $loan = app(\App\Services\Loans\LoanService::class)->record($this->clean, $this->clean->user, ($this->moneyEntry)(['lender_name' => 'Other Bank']));

    $this->actingAs($this->admin)->postJson("/admin/loans/{$loan->uuid}/checks", ['status' => 'confirmed'])->assertStatus(201);

    expect(Notification::where('kind', 'loan.checked')->pluck('user_id')->all())->toBe([$this->clean->user->id]);
});

test('the farmer reads the current result and note on their own loans screens', function () {
    ($this->check)('unreachable', 'Phone off');
    ($this->check)('confirmed', 'Spoke to the manager');

    $list = $this->actingAs($this->farmerUser)->get('/loans')->viewData('page')['props']['loans'][0];
    $page = $this->actingAs($this->farmerUser)->get("/loans/{$this->loan->uuid}")->viewData('page')['props']['loan'];

    foreach ([$list['check'], $page['check']] as $check) {
        expect([$check['status'], $check['note'], $check['date']])->toBe(['confirmed', 'Spoke to the manager', now()->toDateString()])
            ->and(array_keys($check))->not->toContain('by')->not->toContain('id');
    }
});

test('the farmer\'s agent reads it on the farmer\'s page, and the admin on the admin loan page with the history', function () {
    ($this->check)('does_not_match', 'Branch says 800');

    $rows = $this->actingAs($this->agent)->get("/agent/farmers/{$this->profile->uuid}")->viewData('page')['props']['loans'];
    $admin = $this->actingAs($this->admin)->get("/admin/loans/{$this->loan->uuid}")->viewData('page')['props'];

    expect(collect($rows)->firstWhere('uuid', $this->loan->uuid)['check']['note'])->toBe('Branch says 800')
        ->and($admin['loan']['check']['status'])->toBe('does_not_match')
        ->and($admin['checks'])->toHaveCount(1)
        ->and($admin['idempotencyKey'])->not->toBeEmpty();
});

test('a loan nobody has checked has no check anywhere, so the self-reported notice stays', function () {
    $farmer = $this->actingAs($this->farmerUser)->get("/loans/{$this->loan->uuid}")->viewData('page')['props']['loan'];
    $admin = $this->actingAs($this->admin)->get("/admin/loans/{$this->loan->uuid}")->viewData('page')['props'];

    expect($farmer['check'])->toBeNull()->and($admin['loan']['check'])->toBeNull()->and($admin['checks'])->toBe([]);
});

test('nothing in the pages carries a row id or a link into another role\'s pages', function () {
    ($this->check)('confirmed', 'Spoke to the manager');

    $farmer = json_encode($this->actingAs($this->farmerUser)->get("/loans/{$this->loan->uuid}")->viewData('page')['props']['loan']);
    // the page's own data, not the menus and route list every page carries
    $admin = json_encode(array_intersect_key($this->actingAs($this->admin)->get("/admin/loans/{$this->loan->uuid}")->viewData('page')['props'], array_flip(['loan', 'farmer', 'repayments', 'rejected', 'checks', 'idempotencyKey'])));

    expect($farmer)->not->toContain('"id"')->not->toContain('\/admin')->not->toContain('\/agent')
        ->and($admin)->not->toContain('"id"')->not->toContain('"\/loans')->not->toContain('\/agent\/');
});
