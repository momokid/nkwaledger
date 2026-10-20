<?php

// Task 3.6a, part A: loans received in money and opening entries, posted through PostingService by one
// LoanService. The loan's money in is a Liability, never income, so no profit figure moves.

require_once __DIR__ . '/../../Support/Institutions.php';
require_once __DIR__ . '/../../Support/LockedFiguresFixture.php';
require_once __DIR__ . '/../../Support/FigureSnapshot.php';

use App\Exceptions\Ledger\PostingFailed;
use App\Models\AuditLog;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerSubcategory;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Enums\MoneyClass;
use App\Services\Ledger\Reports\AccountStatementService;
use App\Services\Ledger\Reports\IncomeAndExpenditureService;
use App\Services\Loans\LoanService;
use Database\Seeders\LoanTemplateSeeder;

beforeEach(function () {
    buildLockedFigures($this);
    wireFiguresToAnAgent($this);

    // the accounts the loan templates need besides the fixture's own (the real ones come from LedgerAccountSeeder)
    $money = LedgerSubcategory::where('name', 'Money')->first();
    $borrowings = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Liabilities', 'class_id' => LedgerClass::where('name', 'Cr')->first()->id])->id, 'name' => 'Borrowings']);
    $funds = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Equity', 'class_id' => LedgerClass::where('name', 'Cr')->first()->id])->id, 'name' => 'Owner Funds']);

    $account = fn(string $name, int $sub, bool $settlement = false) => LedgerAccount::create([
        'name' => $name, 'control_id' => $this->cash->control_id, 'subcategory_id' => $sub, 'type_id' => $this->cash->type_id, 'is_settlement' => $settlement,
    ]);

    $this->momo = $account('Momo A/C', $money->id, true);
    $this->bank = $account('Bank A/C', $money->id, true);
    $this->loanPayable = $account('Loan Payable', $borrowings->id);
    $this->statedCapital = $account('Stated Capital', $funds->id);

    (new LoanTemplateSeeder())->run();

    $this->loans = app(LoanService::class);

    $this->moneyLoan = fn(array $over = []) => array_merge([
        'kind' => 'money', 'lender_name' => 'Sika Microfinance', 'lender' => institutionNamed('Sika Microfinance', 'microfinance')->uuid,
        'amount' => '500', 'date' => now()->toDateString(), 'paid_into' => $this->cash->id,
    ], $over);

    $this->existingLoan = fn(array $over = []) => array_merge([
        'kind' => 'existing', 'lender_name' => 'Uncle Kofi', 'lender' => institutionNamed('Uncle Kofi', 'other')->uuid, 'amount' => '300',
    ], $over);

    $this->record = fn(array $data, $farmer = null) => $this->loans->record($farmer ?? $this->profile, $this->farmerUser, $data);

    $this->statement = fn($farmer = null) => app(AccountStatementService::class)->for(($farmer ?? $this->profile)->id, $this->from, $this->to);
});

// --- the two templates ---

test('exactly two LOAN templates are seeded, with the accounts and flags the loans page needs', function () {
    $templates = TransactionTemplate::where('transaction_type', 'LOAN')->orderBy('slug')->get();

    $byName = fn(int $id) => LedgerAccount::find($id)->name;

    expect($templates->pluck('slug')->all())->toBe(['loan_already_had', 'loan_received'])
        ->and($templates->every(fn($t) => $t->is_system && $t->is_active && ! $t->requires_farm_unit))->toBeTrue();

    [$already, $received] = [$templates[0], $templates[1]];

    expect([$byName($received->debit_account_id), $byName($received->credit_account_id), $received->settlement_side, $received->is_non_cash, $received->is_liability])
        ->toBe(['Cash A/C', 'Loan Payable', 'debit', false, true])
        ->and([$byName($already->debit_account_id), $byName($already->credit_account_id), $already->settlement_side, $already->is_non_cash])
        ->toBe(['Stated Capital', 'Loan Payable', 'none', true]);
});

test('the seeder fails loudly when an account it needs is missing, and writes nothing', function () {
    TransactionTemplate::where('transaction_type', 'LOAN')->forceDelete();
    LedgerAccount::where('name', 'Loan Payable')->delete();

    expect(fn() => (new LoanTemplateSeeder())->run())->toThrow(RuntimeException::class)
        ->and(TransactionTemplate::where('transaction_type', 'LOAN')->count())->toBe(0);
});

test('seeding twice changes nothing', function () {
    (new LoanTemplateSeeder())->run();

    expect(TransactionTemplate::where('transaction_type', 'LOAN')->count())->toBe(2);
});

// --- a loan received in money ---

test('a money loan is stored, posted as a LOAN record, and its balance is the principal', function () {
    $loan = ($this->record)(($this->moneyLoan)(['interest' => '50', 'due_date' => now()->addDays(30)->toDateString(), 'note' => 'seed money']));

    expect([$loan->kind, $loan->status, $loan->lender_name, $loan->lender_type, $loan->principal_minor, $loan->agreed_interest_minor, $loan->note])
        ->toBe(['money', 'active', 'Sika Microfinance', 'microfinance', 50000, 5000, 'seed money'])
        ->and($loan->uuid)->not->toBeEmpty()
        ->and($loan->settlement_account_id)->toBe($this->cash->id)
        ->and($loan->transaction->transaction_type)->toBe('LOAN')
        ->and($loan->transaction->amount_minor)->toBe(50000)
        ->and($loan->balanceMinor())->toBe(55000)
        ->and((int) $loan->recorded_by)->toBe($this->farmerUser->id);
});

test('a money loan is money in tagged Liability on the statement, and the paid-into account is honoured', function () {
    ($this->record)(($this->moneyLoan)(['paid_into' => $this->momo->id]));

    $row = collect(($this->statement)()->rows)->firstWhere('transactionType', 'LOAN');

    expect([$row->moneyInMinor, $row->moneyOutMinor, $row->moneyClass, $row->isNonCash, $row->accountName])
        ->toBe([50000, 0, MoneyClass::Liability, false, 'Momo A/C']);
});

test('the date may be any past date inside an open period', function () {
    $loan = ($this->record)(($this->moneyLoan)(['date' => now()->subDays(3)->toDateString()]));

    expect($loan->loan_date->toDateString())->toBe(now()->subDays(3)->toDateString())
        ->and($loan->transaction->transaction_date->toDateString())->toBe(now()->subDays(3)->toDateString());
});

// --- a loan the farmer already had ---

test('an opening entry is a non-cash row that raises what is owed and moves no cash', function () {
    $before = ($this->statement)();
    $loan = ($this->record)(($this->existingLoan)(['interest' => '20']));
    $after = ($this->statement)();

    $row = collect($after->rows)->firstWhere('transactionType', 'LOAN');

    expect([$loan->kind, $loan->principal_minor, $loan->agreed_interest_minor, $loan->settlement_account_id, $loan->loan_date->toDateString()])
        ->toBe(['existing', 30000, 2000, null, now()->toDateString()])
        ->and([$row->isNonCash, $row->nonCashMinor, $row->moneyInMinor, $row->moneyOutMinor])->toBe([true, 30000, 0, 0])
        ->and([$after->totalInMinor, $after->totalOutMinor, $after->closingBalanceMinor])
        ->toBe([$before->totalInMinor, $before->totalOutMinor, $before->closingBalanceMinor])
        ->and($after->nonCashMinor)->toBe(30000)
        ->and($loan->balanceMinor())->toBe(32000);
});

// --- the rules ---

test('an amount that is not above zero is refused with the approved words, and nothing is left behind', function (string $amount) {
    $loansBefore = Loan::count();
    $transactionsBefore = Transaction::count();

    expect(fn() => ($this->record)(($this->moneyLoan)(['amount' => $amount])))
        ->toThrow(PostingFailed::class, 'Enter an amount above zero.')
        ->and([Loan::count(), Transaction::count()])->toBe([$loansBefore, $transactionsBefore]);
})->with(['0', '-5', 'abc', '']);

test('a date in the future is refused with the approved words', function () {
    expect(fn() => ($this->record)(($this->moneyLoan)(['date' => now()->addDay()->toDateString()])))
        ->toThrow(PostingFailed::class, 'This date is in the future.');
});

test('agreed interest may be zero or empty but never negative', function () {
    $zero = ($this->record)(($this->moneyLoan)(['interest' => '0']));
    $none = ($this->record)(($this->moneyLoan)(['interest' => null]));

    expect([$zero->agreed_interest_minor, $none->agreed_interest_minor])->toBe([0, null])
        ->and(fn() => ($this->record)(($this->moneyLoan)(['interest' => '-1'])))->toThrow(InvalidArgumentException::class);
});

test('a due date before the loan date is refused', function () {
    expect(fn() => ($this->record)(($this->moneyLoan)(['date' => now()->toDateString(), 'due_date' => now()->subDay()->toDateString()])))
        ->toThrow(InvalidArgumentException::class);
});

test('an opening entry may have a due date that has already passed, since it has no date received', function () {
    $loan = ($this->record)(($this->existingLoan)(['due_date' => now()->subDays(20)->toDateString()]));

    expect($loan->due_date->toDateString())->toBe(now()->subDays(20)->toDateString());
});

test('the statement shows the two template names on the loan rows', function () {
    ($this->record)(($this->moneyLoan)());
    ($this->record)(($this->existingLoan)());

    $descriptions = collect(($this->statement)()->rows)->where('transactionType', 'LOAN')->pluck('description')->sort()->values()->all();

    expect($descriptions)->toBe(['Loan already had', 'Loan received']);
});

test('a posting refusal shows the existing text unchanged, and leaves no loan behind', function () {
    $loansBefore = Loan::count();

    expect(fn() => ($this->record)(($this->moneyLoan)(['date' => now()->subYears(5)->toDateString()])))
        ->toThrow(PostingFailed::class, 'There is no accounting period covering that date.')
        ->and(Loan::count())->toBe($loansBefore);
});

test('the money can only go into cash, MoMo or bank', function () {
    expect(fn() => ($this->record)(($this->moneyLoan)(['paid_into' => $this->receivable->id])))->toThrow(InvalidArgumentException::class)
        ->and(fn() => ($this->record)(($this->moneyLoan)(['paid_into' => 999999])))->toThrow(InvalidArgumentException::class);
});

test('the same submit twice makes one loan and one transaction', function () {
    $data = ($this->moneyLoan)(['idempotency_key' => 'form-abc-123']);

    $first = ($this->record)($data);
    $second = ($this->record)($data);

    expect($second->id)->toBe($first->id)
        ->and(Loan::where('farmer_profile_id', $this->profile->id)->count())->toBe(1)
        ->and(Transaction::where('transaction_type', 'LOAN')->count())->toBe(1);
});

// --- cancelling ---

test('cancelling the loan record cancels the loan in the same step, with balance zero', function () {
    $loan = ($this->record)(($this->moneyLoan)());

    ($this->cancel)($loan->transaction);

    expect($loan->fresh()->status)->toBe('cancelled')
        ->and($loan->fresh()->balanceMinor())->toBe(0);
});

// --- audit ---

test('creating and cancelling a loan are audit-logged', function () {
    $loan = ($this->record)(($this->moneyLoan)());
    ($this->cancel)($loan->transaction);

    $actions = AuditLog::where('auditable_type', Loan::class)->where('auditable_id', $loan->id)->get()->sortBy('id')->pluck('action')->values()->all();

    expect($actions)->toBe(['loan.created', 'loan.cancelled']);
});

// --- no existing figure changes ---

test('loan records change none of the income, expense, loss, net, cash, dashboard, My Farm, admin or agent figures', function () {
    $before = figureSnapshot($this);

    ($this->record)(($this->moneyLoan)());
    ($this->record)(($this->existingLoan)());
    $cancelled = ($this->record)(($this->moneyLoan)(['amount' => '90']));
    ($this->cancel)($cancelled->transaction);

    expect(figureSnapshot($this))->toBe($before);
});

test('the clean farmer signs exactly the same statement code with loans on other farmers (fixed uuid and time)', function () {
    $this->travelTo('2026-10-10 12:00:00');
    $this->clean->forceFill(['uuid' => 'aaaaaaaa-0000-4000-8000-000000000002'])->save();
    $this->profile->forceFill(['uuid' => 'aaaaaaaa-0000-4000-8000-000000000001'])->save();

    $codeOf = fn($farmer) => app(AccountStatementService::class)->for($farmer->id, '2026-10-04', '2026-10-10')->header->verificationCode;
    $incomeCodeOf = fn($farmer) => app(IncomeAndExpenditureService::class)->for($farmer->id, '2026-10-04', '2026-10-10')->header->verificationCode;

    $beforeClean = $codeOf($this->clean);
    $beforeIncome = $incomeCodeOf($this->profile);

    ($this->record)(($this->moneyLoan)(['date' => '2026-10-10']));
    ($this->record)(($this->existingLoan)());

    expect($codeOf($this->clean))->toBe($beforeClean)
        ->and($incomeCodeOf($this->profile))->toBe($beforeIncome);
});
