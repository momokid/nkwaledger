<?php

// Every income, expense, loss, net, cash, dashboard, My Farm, admin and agent figure that must not move
// when loan-type or non-cash records exist. Needs the locked-figures fixture plus $t->agent (assigned to the
// main and clean farmers) and the main farmer in the provisional farmer's community, so every figure sees them.

use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerSubcategory;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Admin\AdminAnalyticsService;
use App\Services\Agent\AgentIncomeSummaryService;
use App\Services\Agent\FarmerRosterService;
use App\Services\Ledger\Reports\IncomeAndExpenditureService;
use Illuminate\Support\Str;

function wireFiguresToAnAgent($t): void
{
    $t->agent = User::factory()->create();
    $t->agent->assignRole('agent');
    $t->profile->update(['assigned_agent_id' => $t->agent->id, 'community_id' => $t->provisional->community_id]);
    $t->clean->update(['assigned_agent_id' => $t->agent->id]);
}

// test-only accounts and templates: nothing here is in a seeder
function addLoanTestTemplates($t): void
{
    $liabilityClass = LedgerClass::where('name', 'Cr')->first();
    $assetSub = LedgerSubcategory::where('name', 'Money')->first();
    $subcategory = LedgerSubcategory::create([
        'category_id' => LedgerCategory::create(['name' => 'Liabilities', 'class_id' => $liabilityClass->id])->id,
        'name' => 'Loans',
    ]);

    $account = fn(string $name, int $sub) => LedgerAccount::create([
        'name' => $name, 'control_id' => $t->cash->control_id, 'subcategory_id' => $sub, 'type_id' => $t->cash->type_id, 'is_settlement' => false,
    ]);

    $t->loanPayable = $account('Loan payable', $subcategory->id);
    $t->goodsStock = $account('Goods in stock', $assetSub->id);

    $template = fn(string $name, int $debit, int $credit, string $side, array $extra = []) => TransactionTemplate::create([
        'name' => $name, 'slug' => Str::slug($name), 'transaction_type' => 'LOAN',
        'debit_account_id' => $debit, 'credit_account_id' => $credit, 'settlement_side' => $side,
        'requires_farm_unit' => true,
    ] + $extra);

    $t->loanReceivedTemplate = $template('Loan received', $t->cash->id, $t->loanPayable->id, 'debit');
    $t->loanRepaidTemplate = $template('Loan principal repaid', $t->loanPayable->id, $t->cash->id, 'credit');
}

// the time a report was made is the only thing that differs between two reads of the same books
function withoutGeneratedAt(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }

    unset($value['generated_at']);

    return array_map('withoutGeneratedAt', $value);
}

function figureSnapshot($t): array
{
    return withoutGeneratedAt(readFigures($t));
}

function readFigures($t): array
{
    $props = function ($user, string $url) {
        $props = null;

        test()->actingAs($user)->get($url)->assertOk()->assertInertia(function ($page) use (&$props) {
            $props = $page->toArray()['props'];
        });

        return $props;
    };

    $report = fn($farmer) => app(IncomeAndExpenditureService::class)->for($farmer->id, $t->from, $t->to);
    $figures = fn($r) => [
        $r->totalIncomeMinor, $r->totalExpenseMinor, $r->totalLossMinor, $r->netMinor,
        $r->cashCollectedMinor, $r->cashPaidOutMinor, $r->assetsAcquiredMinor, $r->provisionalHeldBackMinor,
    ];

    $range = "from={$t->from}&to={$t->to}";
    $agentDashboard = $props($t->agent, "/agent/dashboard?{$range}");
    $farmerDashboard = $props($t->farmerUser, '/farmer/dashboard');

    return [
        'report_main' => $figures($report($t->profile)),
        'report_clean' => $figures($report($t->clean)),
        'farmer_dashboard' => [$farmerDashboard['summary'], $farmerDashboard['breakdown'], $farmerDashboard['recent_transactions']],
        'my_farm' => $props($t->farmerUser, '/my-farm')['units'][0]['analysis'],
        'reports_page_income' => $props($t->farmerUser, "/my-reports?kind=income&{$range}")['report'],
        'agent_dashboard' => [
            $agentDashboard['summary'], $agentDashboard['roster'], $agentDashboard['farmer_count'],
            $agentDashboard['weekly_trend'], $agentDashboard['activity_feed'],
        ],
        'agent_ranking' => $props($t->agent, "/agent/reports/ranking?sort=net&{$range}")['roster'],
        'agent_profile_report' => $props($t->agent, "/agent/farmers/{$t->profile->uuid}/profile-report?{$range}")['summary'],
        'agent_income_summary' => app(AgentIncomeSummaryService::class)->for($t->agent->id, $t->from, $t->to),
        'agent_roster_totals' => app(FarmerRosterService::class)->totalsFor($t->agent->id, $t->from, $t->to, withRows: true),
        'agent_activity_page' => $props($t->agent, "/agent/reports/activity?{$range}"),
        'agent_dormant_page' => $props($t->agent, "/agent/reports/dormant?{$range}"),
        'admin_platform' => app(AdminAnalyticsService::class)->platformSnapshot($t->from, $t->to),
        'admin_leaderboard' => app(AdminAnalyticsService::class)->agentLeaderboard($t->from, $t->to),
        'admin_regional' => app(AdminAnalyticsService::class)->regionalTrends($t->from, $t->to),
        'admin_region_detail' => $t->actingAs($t->admin)->get("/admin/regions/{$t->region->id}/detail?{$range}")->assertOk()->json(),
        'admin_agent_detail' => $t->actingAs($t->admin)->get("/admin/agents/{$t->agent->id}/detail?{$range}")->assertOk()->json(),
        'admin_approvals' => collect($props($t->admin, '/admin/approvals')['items']['data'])
            ->map(fn($row) => $row['details']['provisional_records'] ?? null)->all(),
    ];
}
