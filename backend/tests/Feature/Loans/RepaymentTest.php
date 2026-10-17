<?php

// Task 3.7: a money repayment is one amount from the farmer, split by the server into a principal part
// (a LOAN record) and an interest part (an EXPENSE record), both posted together through PostingService.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Support\Wording;
use App\Enums\MoneyClass;
use App\Exceptions\Ledger\PostingFailed;
use App\Models\AuditLog;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use App\Services\Ledger\Reports\AccountStatementService;
use App\Services\Ledger\Reports\IncomeAndExpenditureService;
use App\Services\Loans\RepaymentService;
use Database\Seeders\RepaymentSeeder;

beforeEach(function () {
    buildLoanWorld($this);

    // P = 100000, I = 20000
    $this->loan = ($this->openLoan)();

    $this->account = fn(string $name) => LedgerAccount::where('name', $name)->firstOrFail();
    $this->net = fn() => app(IncomeAndExpenditureService::class)->for($this->profile->id, $this->from, $this->to);
    $this->payableBalance = fn() => JournalLine::where('ledger_account_id', ($this->account)('Loan Payable')->id)
        ->where('farmer_profile_id', $this->profile->id)
        ->get()->sum(fn($l) => $l->credit_minor - $l->debit_minor);
});

// --- the seeder ---

test('the seeder is idempotent and makes the hidden account and the two templates', function () {
    (new RepaymentSeeder())->run();
    (new RepaymentSeeder())->run();

    $interest = TransactionTemplate::where('slug', 'loan_repayment_interest')->get();
    $principal = TransactionTemplate::where('slug', 'loan_repayment_principal')->get();

    expect(LedgerAccount::where('name', 'Interest and Fees')->count())->toBe(1)
        ->and(($this->account)('Interest and Fees')->is_settlement)->toBeFalse()
        ->and($interest)->toHaveCount(1)->and($principal)->toHaveCount(1)
        ->and([$interest[0]->transaction_type, $interest[0]->is_hidden, $interest[0]->settlement_side])->toBe(['EXPENSE', true, 'credit'])
        ->and([$principal[0]->transaction_type, $principal[0]->settlement_side])->toBe(['LOAN', 'credit']);
});

// --- posting ---

test('a repayment posts a LOAN record for the principal part and an EXPENSE record for the interest part', function () {
    $repayment = ($this->repay)($this->loan, '600');

    $principal = Transaction::find($repayment->principal_transaction_id);
    $interest = Transaction::find($repayment->interest_transaction_id);

    expect([$repayment->amount_minor, $repayment->principal_minor, $repayment->interest_minor])->toBe([60000, 50000, 10000])
        ->and([$principal->transaction_type, $principal->amount_minor, (int) $principal->settlement_account_id])->toBe(['LOAN', 50000, $this->cash->id])
        ->and([$interest->transaction_type, $interest->amount_minor, (int) $interest->settlement_account_id])->toBe(['EXPENSE', 10000, $this->cash->id])
        ->and($interest->template->debit_account_id)->toBe(($this->account)('Interest and Fees')->id)
        ->and((int) $repayment->loan_id)->toBe($this->loan->id)
        ->and($repayment->excess_confirmed)->toBeFalse();
});

test('the principal record is in the Loan repayment class and the interest record is an ordinary expense', function () {
    $repayment = ($this->repay)($this->loan, '600');
    $statement = app(AccountStatementService::class)->for($this->profile->id, $this->from, $this->to);

    $rows = collect($statement->rows)->keyBy('uuid');

    expect($rows[Transaction::find($repayment->principal_transaction_id)->uuid]->moneyClass)->toBe(MoneyClass::LoanRepayment)
        ->and($rows[Transaction::find($repayment->interest_transaction_id)->uuid]->moneyClass)->toBe(MoneyClass::Expenditure)
        ->and($statement->totalLoanRepaymentMinor)->toBe(50000);
});

test('with no agreed interest the whole amount is principal and no interest record is made', function () {
    $loan = ($this->openLoan)(['interest' => null, 'lender_name' => 'No interest lender']);
    $before = Transaction::count();

    $repayment = ($this->repay)($loan, '250');

    expect([$repayment->principal_minor, $repayment->interest_minor, $repayment->interest_transaction_id])->toBe([25000, 0, null])
        ->and(Transaction::count())->toBe($before + 1);
});

test('an interest part of zero makes no interest record', function () {
    $repayment = ($this->repay)($this->loan, '0.01');

    expect([$repayment->principal_minor, $repayment->interest_minor, $repayment->interest_transaction_id])->toBe([1, 0, null]);
});

test('paying off exactly what remains, after drift, leaves exactly nothing owed', function () {
    foreach (['333.33', '333.33', '333.33'] as $amount) {
        ($this->repay)($this->loan, $amount);
    }

    $last = ($this->repay)($this->loan, '200.01');
    $loan = $this->loan->fresh();

    expect($loan->principalRepaidMinor())->toBe(100000)
        ->and($loan->interestPaidMinor())->toBe(20000)
        ->and($loan->balanceMinor())->toBe(0)
        ->and($loan->isRepaid())->toBeTrue()
        ->and($last->excess_confirmed)->toBeFalse();
});

test('net moves by the interest only, the balance by what is paid, and the dashboard figure agrees', function () {
    $beforeNet = ($this->net)()->netMinor;
    $beforeExpense = ($this->net)()->totalExpenseMinor;
    $owedBefore = app(\App\Services\Loans\LoanService::class)->totalOwedMinor($this->profile);

    ($this->repay)($this->loan, '600');

    $after = ($this->net)();
    $dashboard = null;
    $this->actingAs($this->farmerUser)->get('/farmer/dashboard')->assertOk()->assertInertia(function ($page) use (&$dashboard) {
        $dashboard = $page->toArray()['props']['summary']['total_owed'];
    });

    expect($after->totalExpenseMinor - $beforeExpense)->toBe(10000)
        ->and($after->netMinor - $beforeNet)->toBe(-10000)
        ->and($owedBefore)->toBe(120000)
        ->and($this->loan->fresh()->balanceMinor())->toBe(60000)
        ->and($dashboard)->toBe(60000)
        ->and(($this->payableBalance)())->toBe(50000);
});

test('a cancelled record is not counted as paid', function () {
    $repayment = ($this->repay)($this->loan, '600');
    $before = $this->loan->fresh()->balanceMinor();

    $reversal = app(\App\Services\Ledger\ReversalService::class);
    $reversal->approve($reversal->request(Transaction::find($repayment->principal_transaction_id), $this->clerk, 'Typed wrongly'), $this->approver);

    expect($before)->toBe(60000)
        ->and($this->loan->fresh()->principalRepaidMinor())->toBe(0)
        ->and($this->loan->fresh()->balanceMinor())->toBe(110000);
});

test('every repayment is audit-logged with the person who made it', function () {
    $repayment = ($this->repay)($this->loan, '600');

    $log = AuditLog::where('auditable_type', LoanRepayment::class)->where('auditable_id', $repayment->id)->first();

    expect($log->action)->toBe('loan_repayment.created')
        ->and((int) $log->user_id)->toBe($this->farmerUser->id)
        ->and($log->new_values['principal_minor'])->toBe(50000);
});

// --- nothing above what is owed ---

test('more than what remains is refused, and nothing is written', function () {
    $before = [Transaction::count(), LoanRepayment::count()];

    expect(fn() => ($this->repay)($this->loan, '1300'))->toThrow(PostingFailed::class, Wording::get(RepaymentService::OVER_TOTAL))
        ->and([Transaction::count(), LoanRepayment::count()])->toBe($before);
});

test('exactly what remains pays off both parts and leaves nothing owed', function () {
    $repayment = ($this->repay)($this->loan, '1200');

    expect([$repayment->principal_minor, $repayment->interest_minor, $repayment->excess_confirmed])->toBe([100000, 20000, false])
        ->and($this->loan->fresh()->balanceMinor())->toBe(0);
});

test('a confirmation sent along is ignored and cannot get an amount past what is owed', function () {
    $before = Transaction::count();

    expect(fn() => ($this->repay)($this->loan, '1300', ['confirm_excess' => true]))->toThrow(PostingFailed::class, Wording::get(RepaymentService::OVER_TOTAL))
        ->and(Transaction::count())->toBe($before);
});

// --- all or nothing ---

test('both records post together or not at all', function () {
    $real = app(PostingService::class);
    $failing = new class($real) extends PostingService {
        public int $calls = 0;

        public function __construct(private readonly PostingService $inner) {}

        public function post(PostingRequest $request): Transaction
        {
            if (++$this->calls === 2) {
                throw PostingFailed::because('forced');
            }

            return $this->inner->post($request);
        }
    };

    app()->instance(PostingService::class, $failing);
    $service = app()->make(RepaymentService::class);
    $before = [Transaction::count(), LoanRepayment::count(), \App\Models\JournalEntry::count()];

    expect(fn() => $service->record($this->loan, $this->farmerUser, ($this->repayForm)('600')))->toThrow(PostingFailed::class, 'forced')
        ->and([Transaction::count(), LoanRepayment::count(), \App\Models\JournalEntry::count()])->toBe($before)
        ->and($failing->calls)->toBe(2);
});

// --- the rules every record follows ---

test('the amount, the date, the account, the period and the loan are all checked', function () {
    $bad = fn(string $amount, array $over = []) => fn() => ($this->repay)($this->loan, $amount, $over);

    expect($bad('0'))->toThrow(PostingFailed::class, 'Enter an amount above zero.')
        ->and($bad('abc'))->toThrow(PostingFailed::class, 'Enter an amount above zero.')
        ->and($bad('10', ['date' => now()->addDay()->toDateString()]))->toThrow(PostingFailed::class, 'This date is in the future.')
        ->and($bad('10', ['paid_from' => $this->receivable->id]))->toThrow(PostingFailed::class)
        ->and($bad('10', ['paid_from' => 999999]))->toThrow(PostingFailed::class)
        ->and($bad('10', ['date' => now()->subYears(5)->toDateString()]))->toThrow(PostingFailed::class)
        ->and(LoanRepayment::count())->toBe(0);
});

test('a cancelled loan cannot be repaid', function () {
    $this->loan->forceFill(['status' => Loan::CANCELLED])->save();

    expect(fn() => ($this->repay)($this->loan, '10'))->toThrow(PostingFailed::class, Wording::get(RepaymentService::LOAN_NOT_ACTIVE));
});

test('MoMo and Bank are allowed, and an existing loan can be repaid', function () {
    $existing = app(\App\Services\Loans\LoanService::class)->record($this->profile, $this->farmerUser, ($this->existingEntry)(['interest' => '30']));

    $momo = ($this->repay)($this->loan, '10', ['paid_from' => $this->momo->id]);
    $bank = ($this->repay)($existing, '100', ['paid_from' => $this->bank->id]);

    expect((int) Transaction::find($momo->principal_transaction_id)->settlement_account_id)->toBe($this->momo->id)
        ->and((int) Transaction::find($bank->principal_transaction_id)->settlement_account_id)->toBe($this->bank->id)
        ->and($bank->interest_minor)->toBeGreaterThan(0);
});

test('the same key sent twice makes one repayment', function () {
    $first = ($this->repay)($this->loan, '600', ['idempotency_key' => 'same-key']);
    $second = ($this->repay)($this->loan, '600', ['idempotency_key' => 'same-key']);

    expect($second->id)->toBe($first->id)
        ->and(LoanRepayment::count())->toBe(1)
        ->and($this->loan->fresh()->balanceMinor())->toBe(60000);
});

// --- hidden from the form and from sync ---

test('the interest account and its template are not offered by the record form, the paid-into list or sync', function () {
    $this->actingAs($this->farmerUser)->get('/my-records/create')->assertOk()->assertInertia(function ($page) {
        $props = $page->toArray()['props'];

        expect(collect($props['templates'])->pluck('name')->implode('|'))->not->toContain('Interest')
            ->and(collect($props['settlementAccounts'])->pluck('name')->implode('|'))->not->toContain('Interest and Fees');
    });

    $template = TransactionTemplate::where('slug', 'loan_repayment_interest')->first();

    expect(TransactionTemplate::allowedFor($this->profile)->whereKey($template->id)->exists())->toBeFalse()
        ->and(TransactionTemplate::refusalFor($template->id, $this->profile))->not->toBeNull()
        ->and(app(\App\Services\Loans\LoanService::class)->paidIntoOptions()->pluck('label')->all())->toBe(['Cash', 'MoMo', 'Bank']);
});
