<?php

// The shared world for the loan-entry tests: the locked-figures fixture, the ledger accounts and the two LOAN
// templates the real seeders make, an agent assigned to the main farmer, and agents/staff who are not.

require_once __DIR__ . '/LockedFiguresFixture.php';
require_once __DIR__ . '/FigureSnapshot.php';
require_once __DIR__ . '/Institutions.php';

use App\Models\FarmerProfile;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerSubcategory;
use App\Models\User;
use Database\Seeders\LoanTemplateSeeder;
use Database\Seeders\RepaymentSeeder;

function buildLoanWorld($t): void
{
    buildLockedFigures($t);
    // $t->agent is assigned to the main farmer ($t->profile) and the clean farmer
    wireFiguresToAnAgent($t);

    $money = LedgerSubcategory::where('name', 'Money')->first();
    $cr = LedgerClass::where('name', 'Cr')->first();
    $borrowings = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Liabilities', 'class_id' => $cr->id])->id, 'name' => 'Borrowings']);
    $funds = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Equity', 'class_id' => $cr->id])->id, 'name' => 'Owner Funds']);

    $account = fn(string $name, int $sub, bool $settlement = false) => LedgerAccount::create([
        'name' => $name, 'control_id' => $t->cash->control_id, 'subcategory_id' => $sub, 'type_id' => $t->cash->type_id, 'is_settlement' => $settlement,
    ]);

    $t->momo = $account('Momo A/C', $money->id, true);
    $t->bank = $account('Bank A/C', $money->id, true);
    $account('Loan Payable', $borrowings->id);
    $account('Stated Capital', $funds->id);

    (new LoanTemplateSeeder())->run();
    (new RepaymentSeeder())->run();

    $staff = function (string $role) {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    };

    $t->otherAgent = $staff('agent');
    $t->vet = $staff('vet');
    $t->adviser = $staff('adviser');
    $t->otherFarmerUser = $staff('farmer');
    $t->otherFarmer = FarmerProfile::factory()->create(['user_id' => $t->otherFarmerUser->id]);

    $t->institution = institutionNamed('Sika Microfinance');

    // a lender named in a test is an institution in the list, made on the spot, so a test can vary the name
    $t->moneyEntry = function (array $over = []) use ($t) {
        $entry = array_merge([
            'kind' => 'money', 'lender_type' => 'microfinance', 'lender_name' => 'Sika Microfinance',
            'amount' => '500', 'date' => now()->toDateString(), 'paid_into' => $t->cash->id,
        ], $over);

        $entry['lender'] ??= institutionNamed($entry['lender_name'], $entry['lender_type'])->uuid;
        unset($entry['lender_type']);

        return $entry;
    };

    $t->existingEntry = function (array $over = []) {
        $entry = array_merge([
            'kind' => 'existing', 'lender_type' => 'other', 'lender_name' => 'Uncle Kofi', 'amount' => '300',
        ], $over);

        $entry['lender'] ??= institutionNamed($entry['lender_name'], $entry['lender_type'])->uuid;
        unset($entry['lender_type']);

        return $entry;
    };

    $t->entries = app(\App\Services\Loans\PendingEntryService::class);
    $t->codes = app(\App\Services\Loans\EntryCodeService::class);

    // the agent records a money loan for the main farmer
    $t->pendingMoney = fn(array $over = []) => $t->entries->recordLoan($t->profile, $t->agent, ($t->moneyEntry)($over));
    $t->pendingExisting = fn(array $over = []) => $t->entries->recordLoan($t->profile, $t->agent, ($t->existingEntry)($over));

    // the six digits the farmer was sent, read back from the fake SMS service
    $t->lastCode = function () {
        $sent = app(\App\Contracts\SmsProvider::class)->sent;
        preg_match('/code (\d{6}) to your agent/', end($sent)['message'], $match);

        return $match[1];
    };

    // repayments: a loan of 1000 with 200 agreed interest (P = 100000, I = 20000 pesewas), and a repayment form
    $t->repayments = app(\App\Services\Loans\RepaymentService::class);
    $t->openLoan = fn(array $over = []) => app(\App\Services\Loans\LoanService::class)->record($t->profile, $t->farmerUser, ($t->moneyEntry)(array_merge(['amount' => '1000', 'interest' => '200'], $over)));
    $t->repayForm = fn(string $amount, array $over = []) => array_merge([
        'amount' => $amount, 'date' => now()->toDateString(), 'paid_from' => $t->cash->id, 'idempotency_key' => 'r-' . uniqid(),
    ], $over);
    $t->repay = fn($loan, string $amount, array $over = [], $by = null) => $t->repayments->record($loan, $by ?? $t->farmerUser, ($t->repayForm)($amount, $over));
}
