<?php

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
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    config(['app.report_secret' => 'testing-secret']);

    $dr = LedgerClass::create(['name' => 'Dr']);
    $cr = LedgerClass::create(['name' => 'Cr']);
    $assetSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Assets', 'class_id' => $dr->id])->id, 'name' => 'Money']);
    $incomeSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Income', 'class_id' => $cr->id])->id, 'name' => 'Farm Income']);
    $expenseSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Expenses', 'class_id' => $dr->id])->id, 'name' => 'Farm Expense']);
    $control = LedgerControl::create(['name' => 'General']);
    $type = LedgerType::create(['name' => 'GL']);

    $account = fn(string $name, int $sub, bool $settlement = false) => LedgerAccount::create([
        'name' => $name, 'control_id' => $control->id, 'subcategory_id' => $sub, 'type_id' => $type->id, 'is_settlement' => $settlement,
    ]);

    $this->cash = $account('Cash A/C', $assetSub->id, true);
    $sales = $account('Income on Sales', $incomeSub->id);
    $feed = $account('Feed Expense', $expenseSub->id);

    $this->sale = TransactionTemplate::create([
        'name' => 'I sold my farm produce', 'slug' => 'produce_sale', 'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id, 'credit_account_id' => $sales->id, 'settlement_side' => 'debit',
    ]);
    $this->spend = TransactionTemplate::create([
        'name' => 'I bought feed', 'slug' => 'feed_purchase', 'transaction_type' => 'EXPENSE',
        'debit_account_id' => $feed->id, 'credit_account_id' => $this->cash->id, 'settlement_side' => 'credit',
    ]);

    AccountingPeriod::create(['name' => 'This Period', 'starts_on' => now()->startOfYear()->toDateString(), 'ends_on' => now()->endOfYear()->toDateString()]);

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);
});

function csvPost(?string $narration, ?TransactionTemplate $template = null, string $amount = '10'): Transaction
{
    return app(PostingService::class)->post(new PostingRequest(
        farmerProfileId: test()->profile->id,
        transactionTemplateId: ($template ?? test()->sale)->id,
        amount: $amount,
        settlementAccountId: test()->cash->id,
        transactionDate: now()->toDateString(),
        narration: $narration,
        recordedBy: test()->farmerUser->id,
    ));
}

// the whole export read back as a spreadsheet would: rows of cells
function csvRows(): array
{
    $handle = fopen('php://temp', 'w+');
    fwrite($handle, test()->actingAs(test()->farmerUser)->get('/my-reports/csv')->assertOk()->getContent());
    rewind($handle);

    $rows = [];

    while (($row = fgetcsv($handle, escape: '\\')) !== false) {
        $rows[] = $row;
    }

    return $rows;
}

// the description cell of the row with this reference
function descriptionOf(Transaction $transaction): string
{
    return collect(csvRows())->firstWhere(1, $transaction->reference)[2];
}

test('a narration starting with a formula character gets a leading quote', function (string $narration) {
    expect(descriptionOf(csvPost($narration)))->toBe("'" . $narration);
})->with(['=1+1', '+1', '-1', '@SUM(A1)', "\t1", "\r1"]);

test('spaces then a formula character also get the quote', function () {
    expect(descriptionOf(csvPost('  =1+1')))->toBe("'  =1+1");
});

test('normal text, null and empty come out unchanged', function () {
    $normal = csvPost('Sold maize');
    $none = csvPost(null);
    $empty = csvPost('');

    expect(descriptionOf($normal))->toBe('Sold maize')
        ->and(descriptionOf($none))->toBe('I sold my farm produce')
        ->and(descriptionOf($empty))->toBe('I sold my farm produce');
});

test('a negative balance stays exactly as it was', function () {
    csvPost(null, $this->sale, '100');
    $spent = csvPost(null, $this->spend, '250');

    $row = collect(csvRows())->firstWhere(1, $spent->reference);

    expect($row[5])->toBe('-150.00')->and($row[4])->toBe('250.00');
});

test('a template name starting with = is neutralised the same way', function () {
    $this->sale->update(['name' => '=HYPERLINK("http://x")']);

    expect(descriptionOf(csvPost(null)))->toBe("'=HYPERLINK(\"http://x\")");
});

test('commas, quotes and line breaks still read back correctly', function () {
    $text = "Sold \"maize\", 3 bags\nsecond line";

    expect(descriptionOf(csvPost($text)))->toBe($text);
});

test('the figures and the check code do not depend on the text, and are unchanged', function () {
    $risky = csvPost('=1+1');
    $code = fn() => preg_match('/Check code<\/span><strong>([^<]+)</', $this->actingAs($this->farmerUser)->get('/my-reports/print')->getContent(), $m) ? $m[1] : null;
    $totals = fn() => collect(csvRows())->firstWhere(2, 'Totals');

    $before = [$code(), $totals()];

    DB::table('transactions')->where('id', $risky->id)->update(['narration' => 'plain']);

    expect($before[0])->not->toBeNull()
        ->and([$code(), $totals()])->toBe($before)
        ->and($before[1][3])->toBe('10.00');
});
