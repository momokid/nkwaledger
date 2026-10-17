<?php

// The admin's Loan Analysis: the loans added up by region, district, farm type, lender and so on. Admin only.
// Cancelled loans are left out. Every row obeys: amount + interest = repaid + balance.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\Community;
use App\Models\District;
use App\Models\FarmerGroup;
use App\Models\FarmType;
use App\Models\Loan;
use App\Models\Region;
use App\Services\Loans\LoanService;
use App\Services\NavigationAccessService;

beforeEach(function () {
    buildLoanWorld($this);

    // farmer A: North region, Tamale, group Alpha, the agent, farm types Maize and Cattle
    $north = Region::factory()->create(['name' => 'North']);
    $tamale = District::factory()->create(['name' => 'Tamale', 'region_id' => $north->id]);
    $this->profile->update([
        'community_id' => Community::factory()->create(['district_id' => $tamale->id])->id,
        'farmer_group_id' => FarmerGroup::factory()->create(['name' => 'Alpha'])->id,
    ]);
    $maize = FarmType::factory()->create(['name' => 'Maize']);
    $cattle = FarmType::factory()->create(['name' => 'Cattle']);
    $this->profile->farmTypes()->attach([$maize->id, $cattle->id]);

    // farmer B: South region, Kumasi, no group, no agent, farm type Maize
    $south = Region::factory()->create(['name' => 'South']);
    $kumasi = District::factory()->create(['name' => 'Kumasi', 'region_id' => $south->id]);
    $this->clean->update(['community_id' => Community::factory()->create(['district_id' => $kumasi->id])->id, 'farmer_group_id' => null, 'assigned_agent_id' => null]);
    $this->clean->farmTypes()->attach([$maize->id]);

    $loans = app(LoanService::class);
    // A: 1000+200 with 600 paid; 500+50 paid in full. B: 300+30 unpaid, and 200 cancelled
    $this->a1 = ($this->openLoan)(['lender_name' => 'Sika Microfinance', 'lender_type' => 'microfinance']);
    ($this->repay)($this->a1, '600');
    $this->a2 = ($this->openLoan)(['lender_name' => 'Asante Rural Bank', 'lender_type' => 'rural_bank', 'amount' => '500', 'interest' => '50']);
    ($this->repay)($this->a2, '550');
    $this->b1 = $loans->record($this->clean, $this->clean->user, ($this->moneyEntry)(['lender_name' => 'Sika Microfinance', 'lender_type' => 'microfinance', 'amount' => '300', 'interest' => '30']));
    $gone = $loans->record($this->clean, $this->clean->user, ($this->moneyEntry)(['lender_name' => 'Gone Bank', 'amount' => '200']));
    Loan::whereKey($gone->id)->update(['status' => Loan::CANCELLED]);

    $this->analyse = fn(string $query = '') => $this->actingAs($this->admin)->getJson('/admin/loan-analysis' . $query)->assertOk()->json();
    $this->byLabel = fn(string $group, string $extra = '') => collect(($this->analyse)("?group={$group}{$extra}")['rows'])->keyBy('label');
    $this->figures = fn($row) => [$row['loans'], $row['amount'], $row['interest'], $row['repaid'], $row['balance'], $row['repaid_percent']];
});

test('only an admin may open the loan analysis', function (string $who, int $status) {
    $user = ['admin' => $this->admin, 'farmer' => $this->farmerUser, 'agent' => $this->agent, 'vet' => $this->vet, 'adviser' => $this->adviser][$who];

    $this->actingAs($user)->get('/admin/loan-analysis')->assertStatus($status);
})->with([
    'admin' => ['admin', 200],
    'farmer' => ['farmer', 403],
    'agent' => ['agent', 403],
    'vet' => ['vet', 403],
    'adviser' => ['adviser', 403],
]);

test('the totals are over the loans themselves, cancelled loans left out', function () {
    $totals = ($this->analyse)()['totals'];

    expect(($this->figures)($totals))->toBe([3, 180000, 28000, 115000, 93000, 55]);
});

test('by region', function () {
    $rows = ($this->byLabel)('region');

    expect(($this->figures)($rows['North']))->toBe([2, 150000, 25000, 115000, 60000, 66])
        ->and(($this->figures)($rows['South']))->toBe([1, 30000, 3000, 0, 33000, 0])
        ->and($rows)->toHaveCount(2);
});

test('by district', function () {
    $rows = ($this->byLabel)('district');

    expect($rows['Tamale']['loans'])->toBe(2)->and($rows['Kumasi']['loans'])->toBe(1);
});

test('by farm type a loan counts under each of the farmer\'s types, and the totals still count it once', function () {
    $rows = ($this->byLabel)('farm_type');

    expect($rows['Maize']['loans'])->toBe(3)->and($rows['Cattle']['loans'])->toBe(2)
        ->and(($this->analyse)('?group=farm_type')['totals']['loans'])->toBe(3);
});

test('by lender type and by lender', function () {
    $types = ($this->byLabel)('lender_type');
    $lenders = ($this->byLabel)('lender');

    expect($types['Microfinance']['loans'])->toBe(2)->and($types['Rural Bank']['loans'])->toBe(1)
        ->and($lenders['Sika Microfinance']['loans'])->toBe(2)->and($lenders['Asante Rural Bank']['loans'])->toBe(1);
});

test('by farmer group and by agent, with a dash where there is none', function () {
    $groups = ($this->byLabel)('farmer_group');
    $agents = ($this->byLabel)('agent');
    $agentName = trim("{$this->agent->surname} {$this->agent->first_name}");

    expect($groups['Alpha']['loans'])->toBe(2)->and($groups['-']['loans'])->toBe(1)
        ->and($agents[$agentName]['loans'])->toBe(2)->and($agents['-']['loans'])->toBe(1);
});

test('by month, by status and by whether the lender has been asked', function () {
    $this->actingAs($this->admin)->postJson("/admin/loans/{$this->a1->uuid}/checks", ['status' => 'confirmed'])->assertStatus(201);

    $months = ($this->byLabel)('month');
    $status = ($this->byLabel)('status');
    $checked = ($this->byLabel)('checked');

    expect($months[now()->format('Y-m')]['loans'])->toBe(3)
        ->and($status['Active']['loans'])->toBe(2)->and($status['Repaid']['loans'])->toBe(1)
        ->and($checked['Confirmed']['loans'])->toBe(1)->and($checked['-']['loans'])->toBe(2);
});

test('a withdrawn check counts as no check', function () {
    $this->actingAs($this->admin)->postJson("/admin/loans/{$this->a1->uuid}/checks", ['status' => 'confirmed']);
    $this->actingAs($this->admin)->postJson("/admin/loans/{$this->a1->uuid}/checks/withdraw", ['note' => 'Wrong branch']);

    expect(($this->byLabel)('checked')->keys()->all())->toBe(['-']);
});

test('the date range is on the day the loan was taken, both ends included', function () {
    $today = now()->toDateString();

    expect(($this->analyse)("?group=region&from={$today}&to={$today}")['totals']['loans'])->toBe(3)
        ->and(($this->analyse)('?group=region&from=' . now()->addDay()->toDateString())['totals']['loans'])->toBe(0)
        ->and(($this->analyse)('?group=region&to=' . now()->subDay()->toDateString())['rows'])->toBe([]);

    Loan::whereKey($this->a2->id)->update(['loan_date' => now()->subDays(40)->toDateString()]);

    expect(($this->analyse)('?group=region&from=' . now()->subDays(10)->toDateString())['totals']['loans'])->toBe(2);
});

test('in every row what was lent with its interest is what was repaid plus what is still owed', function () {
    foreach (['region', 'district', 'farm_type', 'lender_type', 'lender', 'farmer_group', 'agent', 'month', 'status', 'checked'] as $group) {
        foreach (($this->analyse)("?group={$group}")['rows'] as $row) {
            expect($row['amount'] + $row['interest'])->toBe($row['repaid'] + $row['balance']);
        }
    }
});

test('the figures agree with the loans worked out one by one', function () {
    $balance = Loan::where('status', Loan::ACTIVE)->get()->sum(fn(Loan $loan) => $loan->balanceMinor());

    expect(($this->analyse)()['totals']['balance'])->toBe($balance)->toBe(app(\App\Services\Loans\LoanService::class)->totalOwedMinor($this->profile) + app(\App\Services\Loans\LoanService::class)->totalOwedMinor($this->clean));
});

test('an unknown grouping or a backwards date range is refused', function () {
    $this->actingAs($this->admin)->getJson('/admin/loan-analysis?group=colour')->assertStatus(422);
    $this->actingAs($this->admin)->getJson('/admin/loan-analysis?from=2026-10-10&to=2026-10-01')->assertStatus(422);
    $this->actingAs($this->admin)->getJson('/admin/loan-analysis?from=not-a-date')->assertStatus(422);
});

test('the page opens with the first table already in it, and no row id and no link out of the admin area', function () {
    $props = $this->actingAs($this->admin)->get('/admin/loan-analysis')->viewData('page')['props'];
    $own = json_encode(array_intersect_key($props, array_flip(['rows', 'totals', 'group', 'from', 'to'])));

    expect($props['group'])->toBe('region')->and($props['totals']['loans'])->toBe(3)->and($props['rows'])->toHaveCount(2)
        ->and($own)->not->toContain('"id"')->not->toContain('farmer_profile_id')->not->toContain('\/loans')->not->toContain('\/agent');
});

test('the admin menu lists Loan Analysis between Loans and Lenders', function () {
    $credit = collect(app(NavigationAccessService::class)->adminMenu($this->admin))->firstWhere('label', 'Credit');

    expect(collect($credit['children'])->pluck('label')->all())->toBe(['Loans', 'Loan Analysis', 'Lenders', 'Lender Requests'])
        ->and(collect($credit['children'])->pluck('routeName')->all())->toBe(['admin.loans.index', 'admin.loan-analysis.index', 'admin.lenders.index', 'admin.lender-requests.index']);
});
