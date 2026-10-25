<?php

// Task 6 of credit-score-and-loan-use: a score out of 100 worked out from the farmer's own records, shown to the farmer,
// the agent and the admin. Four pillars: farm purchases 30, money in and out 30, credit and loans 30, loan use 10.
// A pillar with nothing to measure is left out and the others scale up to 100.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\Loan;
use App\Models\ProduceSale;
use App\Models\User;
use App\Services\CreditScoreService;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use App\Services\Loans\LoanService;
use App\Services\Loans\RepaymentService;

beforeEach(function () {
    buildLoanWorld($this);

    // the window runs back across the new year
    AccountingPeriod::create(['name' => 'Last year', 'starts_on' => now()->subYear()->startOfYear()->toDateString(), 'ends_on' => now()->subYear()->endOfYear()->toDateString()]);

    // a farmer with a verified identity and no records, who can record
    $this->scored = FarmerProfile::factory()->create([
        'user_id' => User::factory()->create(['phone_verified_at' => now()])->assignRole('farmer')->id,
        'identity_verified_at' => now(),
        'assigned_agent_id' => $this->agent->id,
    ]);
    $this->scoredUnit = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->scored->id, 'community_id' => $this->scored->community_id]);
    $this->scored->farmTypes()->sync([$this->scoredUnit->farm_type_id ?? \App\Models\FarmType::factory()->create()->id]);

    // the first day of the month n months back (this month: today), so every month holds a record
    $this->day = fn(int $n) => $n === 0 ? now()->toDateString() : now()->startOfMonth()->subMonths($n)->addDay()->toDateString();

    $this->put = fn($template, string $amount, int $monthsBack, ?FarmUnit $unit = null, ?int $account = null) => app(PostingService::class)->post(new PostingRequest(
        farmerProfileId: $this->scored->id,
        transactionTemplateId: $template->id,
        amount: $amount,
        settlementAccountId: $account ?? $this->cash->id,
        transactionDate: ($this->day)($monthsBack),
        farmUnitId: ($unit ?? $this->scoredUnit)->id,
        recordedBy: $this->clerk->id,
    ));

    // both an income and an expense in each of the given months
    $this->months = function (int ...$months) {
        foreach ($months as $n) {
            ($this->put)($this->saleTemplate, '100', $n);
            ($this->put)($this->feedTemplate, '40', $n);
        }
    };

    $this->score = fn() => app(CreditScoreService::class)->for($this->scored->fresh());
    $this->pillar = fn(string $key) => collect(($this->score)()['pillars'])->firstWhere('key', $key);
});

test('no score without a verified identity, and the farmer is told how to get one', function () {
    $this->scored->update(['identity_verified_at' => null]);
    ($this->months)(0, 1, 2);

    $score = ($this->score)();

    expect($score['status'])->toBe('unavailable');
    expect(app(CreditScoreService::class)->display($this->scored->fresh(), 'staff')['text'])->toBe('Not yet available');
    expect(app(CreditScoreService::class)->display($this->scored->fresh(), 'farmer')['text'])->toBe('Not yet available. Your agent can help you confirm your identity document.');
});

test('fewer than 3 months of records says so, in the words for each reader', function () {
    ($this->months)(0, 1);

    expect(($this->score)()['status'])->toBe('not_enough');
    expect(app(CreditScoreService::class)->display($this->scored->fresh(), 'staff')['text'])->toBe('Not enough records yet. We need records in at least 3 of the last 12 months.');
    expect(app(CreditScoreService::class)->display($this->scored->fresh(), 'farmer')['text'])->toBe('Not enough records yet. Keep recording: we need records in at least 3 of the last 12 months.');
});

test('regular records in every month give the full score, with the pillars nothing measures left out', function () {
    ($this->months)(...range(0, 11));

    $score = ($this->score)();

    expect([$score['status'], $score['score'], $score['band']])->toBe(['ok', 100, 'Strong']);
    expect(($this->pillar)('purchases'))->toMatchArray(['counted' => true, 'points' => 50, 'max' => 50, 'months' => 12]);
    expect(($this->pillar)('credit')['counted'])->toBeFalse();
    expect(($this->pillar)('loan_use')['counted'])->toBeFalse();

    $shown = app(CreditScoreService::class)->display($this->scored->fresh(), 'staff');

    expect($shown['text'])->toBe('100 out of 100 (Strong)');
    expect($shown['lines'])->toBe([
        'Farm purchases: 50 out of 50. Recorded in 12 of the last 12 months.',
        'Money in and out: 50 out of 50. Both recorded in 12 of the last 12 months.',
        'Credit and loans: not counted yet.',
        'Loan money used on the farm: not counted yet.',
    ]);
    expect($shown['note'])->toBe('This score is a guide only. It does not decide a loan.');
});

test('half the months give half the score, and the bands fall where agreed', function () {
    ($this->months)(...range(0, 5));

    expect([($this->score)()['score'], ($this->score)()['band']])->toBe([50, 'Fair']);

    expect(array_map(fn($n) => app(CreditScoreService::class)->bandFor($n), [0, 39, 40, 59, 60, 79, 80, 100]))
        ->toBe(['Building', 'Building', 'Fair', 'Fair', 'Good', 'Good', 'Strong', 'Strong']);
});

test('a month with an income but no expense counts for purchases never, and for money in and out never', function () {
    foreach (range(0, 3) as $n) {
        ($this->put)($this->saleTemplate, '100', $n);
    }

    expect(($this->pillar)('purchases')['months'])->toBe(0);
    expect(($this->pillar)('cash')['months'])->toBe(0);
    expect(($this->score)()['status'])->toBe('ok');
});

test('provisional and cancelled records do not count', function () {
    ($this->months)(0, 1, 2);
    $unapproved = FarmUnit::factory()->create(['farmer_profile_id' => $this->scored->id, 'community_id' => $this->scored->community_id]);

    foreach (range(3, 8) as $n) {
        ($this->put)($this->saleTemplate, '100', $n, $unapproved);
        ($this->put)($this->feedTemplate, '40', $n, $unapproved);
    }

    $cancelled = ($this->put)($this->feedTemplate, '10', 9);
    ($this->cancel)($cancelled);

    expect(($this->pillar)('purchases')['months'])->toBe(3);
});

test('a produce sale that is not vouched for by an agent does not count, and one that is does', function () {
    ($this->months)(0, 1);
    $sale = ($this->put)($this->saleTemplate, '100', 5);
    ($this->put)($this->feedTemplate, '40', 5);

    $produce = ProduceSale::factory()->fullyConfirmed()->create(['farmer_profile_id' => $this->scored->id, 'ledger_transaction_id' => $sale->id]);

    // the sale is the month's only income, so the month does not count while it is not vouched for
    expect(($this->pillar)('cash')['months'])->toBe(2);

    $produce->forceFill(['agent_co_confirmed_at' => now()])->save();

    expect(($this->pillar)('cash')['months'])->toBe(3);
});

test('credit and loans: payments early or on time count, late ones do not', function () {
    ($this->months)(...range(0, 5));

    $loan = app(LoanService::class)->record($this->scored, $this->scored->user, ($this->moneyEntry)([
        'amount' => '1000', 'interest' => '0', 'date' => ($this->day)(3), 'due_date' => ($this->day)(1),
    ]));

    app(RepaymentService::class)->record($loan, $this->scored->user, ($this->repayForm)('600', ['date' => ($this->day)(2)]));
    app(RepaymentService::class)->record($loan, $this->scored->user, ($this->repayForm)('400', ['date' => now()->subDays(3)->toDateString()]));

    $credit = ($this->pillar)('credit');

    expect($credit)->toMatchArray(['counted' => true, 'paid' => 2, 'on_time' => 1, 'overdue' => 0]);
    expect(round($credit['raw_points'], 1))->toBe(15.0);
    expect(app(CreditScoreService::class)->display($this->scored->fresh(), 'staff')['lines'][2])->toContain('1 of 2 payments were early or on time.');
});

test('an overdue loan with no payments scores nothing and is named', function () {
    ($this->months)(...range(0, 5));

    app(LoanService::class)->record($this->scored, $this->scored->user, ($this->moneyEntry)([
        'amount' => '500', 'interest' => '0', 'date' => ($this->day)(3), 'due_date' => now()->subDay()->toDateString(),
    ]));

    $credit = ($this->pillar)('credit');

    expect($credit)->toMatchArray(['counted' => true, 'paid' => 0, 'overdue' => 1]);
    expect($credit['raw_points'])->toBe(0.0);
    expect(app(CreditScoreService::class)->display($this->scored->fresh(), 'staff')['lines'][2])->toContain('1 loan(s) overdue.');
});

test('loan money used on the farm counts once a loan is a week old', function () {
    ($this->months)(...range(0, 5));

    $loan = app(LoanService::class)->record($this->scored, $this->scored->user, ($this->moneyEntry)([
        'amount' => '500', 'interest' => '0', 'date' => ($this->day)(1),
        'farms' => [['farm_unit' => $this->scoredUnit->uuid, 'amount' => '500']],
    ]));

    expect(($this->pillar)('loan_use')['counted'])->toBeFalse();

    $loan->forceFill(['created_at' => now()->subDays(8)])->save();
    app(PostingService::class)->post(new PostingRequest(
        farmerProfileId: $this->scored->id, transactionTemplateId: $this->feedTemplate->id, amount: '300', settlementAccountId: $this->cash->id,
        transactionDate: now()->toDateString(), farmUnitId: $this->scoredUnit->id, recordedBy: $this->clerk->id, loanId: $loan->id,
    ));

    $use = ($this->pillar)('loan_use');

    expect($use)->toMatchArray(['counted' => true, 'percent' => 60]);
    expect(round($use['raw_points'], 1))->toBe(6.0);
    expect(collect(app(CreditScoreService::class)->display($this->scored->fresh(), 'staff')['lines'])->last())->toContain('60% accounted for.');
});

test('the agent report, its print page and the farmer dashboard show the score, and the made-up 720 is gone', function () {
    ($this->months)(...range(0, 11));
    $url = "/agent/farmers/{$this->scored->uuid}/profile-report";

    $this->actingAs($this->agent)->get($url)->assertInertia(function ($page) {
        expect($page->toArray()['props']['credit_score']['text'])->toBe('100 out of 100 (Strong)');
    });

    $this->actingAs($this->agent)->get("{$url}/print")->assertOk()->assertSee('100 out of 100 (Strong)')->assertSee('This score is a guide only. It does not decide a loan.');

    $this->actingAs($this->scored->user)->get('/farmer/dashboard')->assertInertia(function ($page) {
        expect($page->toArray()['props']['credit_score']['text'])->toBe('100 out of 100 (Strong)');
    });
});

test('a farmer who is not yet scored sees Not yet available on the dashboard', function () {
    $this->actingAs($this->farmerUser)->get('/farmer/dashboard')->assertInertia(function ($page) {
        expect($page->toArray()['props']['credit_score']['text'])->toBeIn(['Not yet available', 'Not yet available. Your agent can help you confirm your identity document.']);
    });
});
