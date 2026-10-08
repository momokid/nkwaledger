<?php

// "active farmers", "records logged" and "last activity" count only records that really happened:
// not a cancelled record, not a correction row, and not a cancelled credit payment. A live payment counts.

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Admin\AdminAnalyticsService;
use App\Services\Agent\FarmerRosterService;
use App\Services\Ledger\CreditSettlementService;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use App\Services\Ledger\ReversalService;

beforeEach(function () {
    $dr = LedgerClass::create(['name' => 'Dr']);
    $cr = LedgerClass::create(['name' => 'Cr']);
    $assetSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Assets', 'class_id' => $dr->id])->id, 'name' => 'Money']);
    $incomeSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Income', 'class_id' => $cr->id])->id, 'name' => 'Farm Income']);
    $expenseSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Expenses', 'class_id' => $dr->id])->id, 'name' => 'Farm Expenses']);
    $control = LedgerControl::create(['name' => 'General']);
    $type = LedgerType::create(['name' => 'GL']);

    $account = fn(string $name, int $sub, bool $settlement = false) => LedgerAccount::create([
        'name' => $name, 'control_id' => $control->id, 'subcategory_id' => $sub, 'type_id' => $type->id, 'is_settlement' => $settlement,
    ]);

    $this->cash = $account('Cash A/C', $assetSub->id, true);
    $this->receivable = $account('Accounts Receivable', $assetSub->id, true);
    $account('Accounts Payable', $assetSub->id, true);
    $sales = $account('Sales A/C', $incomeSub->id);
    $feed = $account('Feed A/C', $expenseSub->id);

    // from last year, so a record 40 days back is inside the period whatever today is
    AccountingPeriod::create(['name' => 'Test', 'starts_on' => now()->subYear()->startOfYear()->toDateString(), 'ends_on' => now()->endOfYear()->toDateString()]);

    $this->sale = TransactionTemplate::create(['name' => 'sale', 'slug' => 'sale', 'transaction_type' => 'INCOME', 'debit_account_id' => $this->cash->id, 'credit_account_id' => $sales->id, 'settlement_side' => 'debit', 'allows_credit' => true]);
    $this->spend = TransactionTemplate::create(['name' => 'spend', 'slug' => 'spend', 'transaction_type' => 'EXPENSE', 'debit_account_id' => $feed->id, 'credit_account_id' => $this->cash->id, 'settlement_side' => 'credit']);
    TransactionTemplate::create(['name' => 'Payment received', 'slug' => 'payment_received', 'transaction_type' => 'ADJUSTMENT', 'debit_account_id' => $this->cash->id, 'credit_account_id' => $this->receivable->id, 'settlement_side' => 'debit']);
    TransactionTemplate::create(['name' => 'correction', 'slug' => 'correction', 'transaction_type' => 'ADJUSTMENT', 'debit_account_id' => $this->cash->id, 'credit_account_id' => $sales->id, 'settlement_side' => 'none']);

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');
    $this->clerk = User::factory()->create();
    $this->approver = User::factory()->create();

    $farmer = fn() => FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

    $this->x = $farmer(); // only a cancelled sale today
    $this->y = $farmer(); // a live sale today and a cancelled expense today
    $this->z = $farmer(); // a credit sale 40 days ago and a live payment today
    $this->w = $farmer(); // a credit sale 40 days ago and a payment today that is cancelled
    $this->v = $farmer(); // a live sale 3 days ago and a cancelled sale today

    $this->put = fn(FarmerProfile $who, TransactionTemplate $template, string $amount, int $daysAgo = 0, bool $onCredit = false) => app(PostingService::class)->post(new PostingRequest(
        farmerProfileId: $who->id,
        transactionTemplateId: $template->id,
        amount: $amount,
        settlementAccountId: $onCredit ? $this->receivable->id : $this->cash->id,
        transactionDate: now()->subDays($daysAgo)->toDateString(),
        recordedBy: $this->clerk->id,
    ));

    $this->pay = fn(Transaction $creditSale) => app(CreditSettlementService::class)
        ->settle($creditSale, 1000, $this->cash->id, now()->toDateString(), $this->clerk->id);

    $this->cancel = function (Transaction $transaction) {
        $service = app(ReversalService::class);
        $service->approve($service->request($transaction, $this->clerk, 'Typed wrongly'), $this->approver);
    };

    ($this->cancel)(($this->put)($this->x, $this->sale, '50'));

    ($this->put)($this->y, $this->sale, '60');
    ($this->cancel)(($this->put)($this->y, $this->spend, '20'));

    ($this->pay)(($this->put)($this->z, $this->sale, '100', 40, true));

    ($this->cancel)(($this->pay)(($this->put)($this->w, $this->sale, '100', 40, true)));

    ($this->put)($this->v, $this->sale, '70', 3);
    ($this->cancel)(($this->put)($this->v, $this->sale, '30'));

    $this->from = now()->subDays(6)->toDateString();
    $this->to = now()->toDateString();
});

test('active farmers: a cancelled record, its correction and a cancelled payment do not make a farmer active; a live payment does', function () {
    $snapshot = app(AdminAnalyticsService::class)->platformSnapshot($this->from, $this->to);

    // y (live sale), z (live payment) and v (live sale 3 days ago); not x, and not w
    expect($snapshot['active_farmers'])->toBe(3);
});

test('records logged: only live records are counted for the agent', function () {
    $row = collect(app(AdminAnalyticsService::class)->agentLeaderboard($this->from, $this->to))->firstWhere('agent_id', $this->agent->id);

    // y's sale, z's live payment and v's sale 3 days ago
    expect($row['records_logged'])->toBe(3);
});

test('last activity: the date of the latest live record, never a cancelled one or its correction', function () {
    [, , , $rows] = app(FarmerRosterService::class)->totalsFor($this->agent->id, $this->from, $this->to, withRows: true);

    $last = collect($rows)->mapWithKeys(fn($row) => [$row['farmer_profile_id'] => $row['last_activity']])->all();

    expect([
        'x_only_cancelled' => $last[$this->x->id],
        'y_live_today' => $last[$this->y->id],
        'z_live_payment_today' => $last[$this->z->id],
        'w_cancelled_payment' => $last[$this->w->id],
        'v_live_three_days_ago' => $last[$this->v->id],
    ])->toBe([
        'x_only_cancelled' => null,
        'y_live_today' => now()->toDateString(),
        'z_live_payment_today' => now()->toDateString(),
        'w_cancelled_payment' => now()->subDays(40)->toDateString(),
        'v_live_three_days_ago' => now()->subDays(3)->toDateString(),
    ]);
});
