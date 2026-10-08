<?php

// One shared set of records for the tests that lock today's figures. Every record is dated today,
// inside one open period, so nothing here depends on a calendar date.
//
// Main farmer (all pesewas are cedis x 100):
//   1  cash sale 1000            2  feed expense 400          3  animal purchase 300 (asset)
//   4  animal loss 120 (2 lost)  5  credit sale 500           6  payment of 200 on the credit sale
//   7  cash sale 250 - cancelled 8  feed expense 150 - cancelled
//   9  payment of 100 on the credit sale - cancelled
// Clean farmer: records 1 to 4 only, nothing cancelled.

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Ledger\CreditSettlementService;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use App\Services\Ledger\ReversalService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function buildLockedFigures($t): void
{
    Cache::flush();
    $t->seed(RolesAndPermissionsSeeder::class);
    $t->seed(PermissionsSeeder::class);
    config(['app.report_secret' => 'locked-figures-secret']);

    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response(['results' => [['latitude' => 6.7, 'longitude' => -1.5]]]),
        'api.open-meteo.com/*' => Http::response(['daily' => [
            'precipitation_sum' => [2, 5, 3],
            'temperature_2m_max' => [28, 29, 27],
            'windspeed_10m_max' => [15, 18, 12],
        ]]),
    ]);

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

    $t->cash = $account('Cash A/C', $assetSub->id, true);
    $t->receivable = $account('Accounts Receivable', $assetSub->id, true);
    $account('Accounts Payable', $assetSub->id, true);
    $sales = $account('Sales A/C', $incomeSub->id);
    $feed = $account('Feed A/C', $expenseSub->id);
    $livestock = $account('Livestock A/C', $assetSub->id);
    $lossAccount = $account('Loss A/C', $expenseSub->id);

    AccountingPeriod::create([
        'name' => 'Locked Figures Period',
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
    ]);

    $template = fn(string $name, string $kind, int $debit, int $credit, string $side, array $extra = []) => TransactionTemplate::create([
        'name' => $name, 'slug' => Str::slug($name), 'transaction_type' => $kind,
        'debit_account_id' => $debit, 'credit_account_id' => $credit, 'settlement_side' => $side,
        'requires_farm_unit' => true,
    ] + $extra);

    $t->saleTemplate = $template('Cash sale', 'INCOME', $t->cash->id, $sales->id, 'debit', ['allows_credit' => true]);
    $t->feedTemplate = $template('Feed expense', 'EXPENSE', $feed->id, $t->cash->id, 'credit');
    $t->purchaseTemplate = $template('Animal purchase', 'EXPENSE', $livestock->id, $t->cash->id, 'credit', ['is_stock_purchase' => true, 'stock_source' => 'purchase']);
    $t->lossTemplate = $template('Animal loss', 'LOSS', $lossAccount->id, $livestock->id, 'none');
    TransactionTemplate::create(['name' => 'Payment received', 'slug' => 'payment_received', 'transaction_type' => 'ADJUSTMENT', 'debit_account_id' => $t->cash->id, 'credit_account_id' => $t->receivable->id, 'settlement_side' => 'debit']);
    TransactionTemplate::create(['name' => 'Correction', 'slug' => 'correction', 'transaction_type' => 'ADJUSTMENT', 'debit_account_id' => $t->cash->id, 'credit_account_id' => $sales->id, 'settlement_side' => 'none']);

    $t->admin = User::factory()->create();
    $t->admin->assignRole('admin');
    $t->clerk = User::factory()->create();
    $t->approver = User::factory()->create();

    $t->farmerUser = User::factory()->create();
    $t->farmerUser->assignRole('farmer');
    $t->profile = FarmerProfile::factory()->create(['user_id' => $t->farmerUser->id]);
    $t->clean = FarmerProfile::factory()->create();

    $t->unitOf = function (FarmerProfile $farmer) {
        $unit = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $farmer->id]);
        FarmUnitStock::factory()->confirmed()->create(['farm_unit_id' => $unit->id, 'opening_quantity' => 100]);

        return $unit;
    };

    $t->unit = ($t->unitOf)($t->profile);
    $cleanUnit = ($t->unitOf)($t->clean);

    $posting = app(PostingService::class);

    $t->put = fn(FarmerProfile $farmer, FarmUnit $unit, TransactionTemplate $template, string $amount, ?int $settleWith = null, array $quantities = []) => $posting->post(new PostingRequest(
        farmerProfileId: $farmer->id,
        transactionTemplateId: $template->id,
        amount: $amount,
        settlementAccountId: $template->settlement_side === 'none' ? null : ($settleWith ?? $t->cash->id),
        transactionDate: now()->toDateString(),
        farmUnitId: $unit->id,
        recordedBy: $t->clerk->id,
        quantityLost: $quantities['lost'] ?? null,
        quantityPurchased: $quantities['bought'] ?? null,
        unitOfMeasure: 'birds',
    ));

    $t->cancel = function (Transaction $transaction) use ($t) {
        $service = app(ReversalService::class);
        $service->approve($service->request($transaction, $t->clerk, 'Typed wrongly'), $t->approver);
    };

    $settle = fn(Transaction $sale, int $minor) => app(CreditSettlementService::class)
        ->settle($sale, $minor, $t->cash->id, now()->toDateString(), $t->clerk->id);

    // the clean farmer: records 1 to 4
    ($t->put)($t->clean, $cleanUnit, $t->saleTemplate, '1000');
    ($t->put)($t->clean, $cleanUnit, $t->feedTemplate, '400');
    ($t->put)($t->clean, $cleanUnit, $t->purchaseTemplate, '300', null, ['bought' => '5']);
    ($t->put)($t->clean, $cleanUnit, $t->lossTemplate, '120', null, ['lost' => '2']);

    // the main farmer: records 1 to 9, then the three cancellations
    ($t->put)($t->profile, $t->unit, $t->saleTemplate, '1000');
    ($t->put)($t->profile, $t->unit, $t->feedTemplate, '400');
    ($t->put)($t->profile, $t->unit, $t->purchaseTemplate, '300', null, ['bought' => '5']);
    ($t->put)($t->profile, $t->unit, $t->lossTemplate, '120', null, ['lost' => '2']);
    $t->creditSale = ($t->put)($t->profile, $t->unit, $t->saleTemplate, '500', $t->receivable->id);
    $settle($t->creditSale, 20000);
    $t->cancelledSale = ($t->put)($t->profile, $t->unit, $t->saleTemplate, '250');
    $t->cancelledExpense = ($t->put)($t->profile, $t->unit, $t->feedTemplate, '150');
    $t->cancelledPayment = $settle($t->creditSale, 10000);

    ($t->cancel)($t->cancelledSale);
    ($t->cancel)($t->cancelledExpense);
    ($t->cancel)($t->cancelledPayment);

    $t->from = now()->subDays(6)->toDateString();
    $t->to = now()->toDateString();
}
