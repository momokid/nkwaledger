<?php

// The shared world for the loan-entry tests: the locked-figures fixture, the ledger accounts and the two LOAN
// templates the real seeders make, an agent assigned to the main farmer, and agents/staff who are not.

require_once __DIR__ . '/LockedFiguresFixture.php';
require_once __DIR__ . '/FigureSnapshot.php';

use App\Models\FarmerProfile;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerSubcategory;
use App\Models\User;
use Database\Seeders\LoanTemplateSeeder;

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

    $t->moneyEntry = fn(array $over = []) => array_merge([
        'kind' => 'money', 'lender_name' => 'Sika Microfinance', 'lender_type' => 'microfinance',
        'amount' => '500', 'date' => now()->toDateString(), 'paid_into' => $t->cash->id,
    ], $over);

    $t->existingEntry = fn(array $over = []) => array_merge([
        'kind' => 'existing', 'lender_name' => 'Uncle Kofi', 'lender_type' => 'individual', 'amount' => '300',
    ], $over);

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
}
