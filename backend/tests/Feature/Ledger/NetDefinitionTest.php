<?php

// net is income minus expense minus loss, in every place that shows or works out a net.
// The shared fixture has a loss of 120.00 on the main farmer and on the clean farmer, so a net that
// forgets the loss comes out 12000 (or 24000 across both farmers) too high. The 300.00 animal purchase is an
// asset gained, not an expense, so it is in none of these nets (they were 30000 per farmer lower before).

require_once __DIR__ . '/../../Support/LockedFiguresFixture.php';

use App\Models\User;
use App\Services\Admin\AdminAnalyticsService;
use App\Services\Agent\AgentIncomeSummaryService;
use App\Services\Agent\FarmerRosterService;
use App\Services\Ledger\Reports\IncomeAndExpenditure;
use App\Services\Ledger\Reports\IncomeAndExpenditureService;

beforeEach(function () {
    buildLockedFigures($this);

    // main farmer: income 150000, expense 40000, loss 12000 -> net 98000
    // clean farmer: income 100000, expense 40000, loss 12000 -> net 48000
    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');
    $this->profile->update(['assigned_agent_id' => $this->agent->id, 'community_id' => $this->provisional->community_id]);
    $this->clean->update(['assigned_agent_id' => $this->agent->id]);

    $this->props = function ($user, string $url) {
        $props = null;

        $this->actingAs($user)->get($url)->assertOk()->assertInertia(function ($page) use (&$props) {
            $props = $page->toArray()['props'];
        });

        return $props;
    };
});

test('the one definition: income minus expense minus loss', function () {
    expect(IncomeAndExpenditure::netOf(150000, 40000, 12000))->toBe(98000);
});

test('income and expenditure report net', function () {
    expect(app(IncomeAndExpenditureService::class)->for($this->profile->id, $this->from, $this->to)->netMinor)->toBe(98000);
});

test('farmer dashboard net', function () {
    expect(($this->props)($this->farmerUser, '/farmer/dashboard')['summary']['net'])->toBe(98000);
});

test('my farm net', function () {
    expect(($this->props)($this->farmerUser, '/my-farm')['units'][0]['analysis']['net'])->toBe(98000);
});

test('reports page net', function () {
    expect(($this->props)($this->farmerUser, "/my-reports?kind=income&from={$this->from}&to={$this->to}")['report']['net'])->toBe(98000);
});

test('agent income summary net, with the loss shown on its own', function () {
    $summary = app(AgentIncomeSummaryService::class)->for($this->agent->id, $this->from, $this->to);

    expect([$summary['total_income'], $summary['total_expense'], $summary['total_loss'], $summary['net']])
        ->toBe([250000, 80000, 24000, 146000]);
});

test('agent roster rows carry the net of each farmer', function () {
    [, , , $rows, , , $loss] = app(FarmerRosterService::class)->totalsFor($this->agent->id, $this->from, $this->to, withRows: true);

    expect([
        'nets' => collect($rows)->mapWithKeys(fn($row) => [$row['farmer_profile_id'] => $row['net']])->sortKeys()->all(),
        'loss' => $loss,
    ])->toBe(['nets' => [$this->profile->id => 98000, $this->clean->id => 48000], 'loss' => 24000]);
});

test('agent dashboard net', function () {
    expect(($this->props)($this->agent, '/agent/dashboard')['summary']['net'])->toBe(146000);
});

test('admin agent detail net', function () {
    $json = $this->actingAs($this->admin)->get("/admin/agents/{$this->agent->id}/detail?from={$this->from}&to={$this->to}")->assertOk()->json();

    expect($json['summary']['net'])->toBe(146000);
});

test('agent ranking by net uses the net with the loss taken off', function () {
    $rows = ($this->props)($this->agent, "/agent/reports/ranking?sort=net&from={$this->from}&to={$this->to}")['roster'];

    expect(collect($rows)->map(fn($row) => [$row['rank'], $row['net']])->all())->toBe([[1, 98000], [2, 48000]]);
});

test('agent farmer profile report net', function () {
    $summary = ($this->props)($this->agent, "/agent/farmers/{$this->profile->uuid}/profile-report?from={$this->from}&to={$this->to}")['summary'];

    expect([$summary['total_loss'], $summary['net']])->toBe([12000, 98000]);
});

test('admin leaderboard net per agent', function () {
    $row = collect(app(AdminAnalyticsService::class)->agentLeaderboard($this->from, $this->to))->firstWhere('agent_id', $this->agent->id);

    expect($row['net'])->toBe(146000);
});

test('admin regional trends net', function () {
    $row = collect(app(AdminAnalyticsService::class)->regionalTrends($this->from, $this->to))->firstWhere('region_id', $this->region->id);

    // main farmer 98000 plus the provisional farmer's live sale of 100.00
    expect([$row['income'], $row['expense'], $row['net']])->toBe([160000, 40000, 108000]);
});

test('admin platform net', function () {
    $snapshot = app(AdminAnalyticsService::class)->platformSnapshot($this->from, $this->to);

    expect([$snapshot['total_income'], $snapshot['total_expense'], $snapshot['net']])->toBe([250000, 80000, 146000]);
});
