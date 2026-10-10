<?php

// buying stock (an animal, a seedling) is an asset gained, not a cost of running the farm: it is left out of
// the expense, and so out of net, in every place that adds up expense, while the cash that left stays in cash
// paid out. The shared fixture has one 300.00 animal purchase on the main farmer and one on the clean farmer.

require_once __DIR__ . '/../../Support/LockedFiguresFixture.php';

use App\Models\User;
use App\Services\Ledger\Reports\IncomeAndExpenditureService;

beforeEach(function () {
    buildLockedFigures($this);

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');
    $this->profile->update(['assigned_agent_id' => $this->agent->id, 'community_id' => $this->provisional->community_id]);
    $this->clean->update(['assigned_agent_id' => $this->agent->id]);
});

test('the report keeps the purchase out of expense and shows it as an asset gained', function () {
    $r = app(IncomeAndExpenditureService::class)->for($this->profile->id, $this->from, $this->to);

    expect([$r->totalExpenseMinor, $r->assetsAcquiredMinor, $r->cashPaidOutMinor])->toBe([40000, 30000, 70000]);
});

test('admin region detail: the farmer expense leaves the purchase out', function () {
    $farmers = collect($this->actingAs($this->admin)
        ->get("/admin/regions/{$this->region->id}/detail?from={$this->from}&to={$this->to}")
        ->assertOk()->json('farmers'));

    expect($farmers->firstWhere('id', $this->profile->uuid)['expense'])->toBe(40000);
});

test('agent weekly trend: the expense buckets leave the purchases out', function () {
    $trend = $this->actingAs($this->agent)->get("/agent/dashboard?from={$this->from}&to={$this->to}")
        ->assertOk()->viewData('page')['props']['weekly_trend'];

    expect(collect($trend)->sum('expense'))->toBe(80000);
});
