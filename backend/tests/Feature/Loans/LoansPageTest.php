<?php

// Task 3.6a, part B: the farmer's Loans page, the two forms, the dashboard figure.

require_once __DIR__ . '/../../Support/LockedFiguresFixture.php';
require_once __DIR__ . '/../../Support/FigureSnapshot.php';

use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerSubcategory;
use App\Models\Loan;
use App\Services\Loans\LoanService;
use Database\Seeders\LoanTemplateSeeder;

beforeEach(function () {
    buildLockedFigures($this);

    $money = LedgerSubcategory::where('name', 'Money')->first();
    $cr = LedgerClass::where('name', 'Cr')->first();
    $borrowings = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Liabilities', 'class_id' => $cr->id])->id, 'name' => 'Borrowings']);
    $funds = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Equity', 'class_id' => $cr->id])->id, 'name' => 'Owner Funds']);

    $account = fn(string $name, int $sub, bool $settlement = false) => LedgerAccount::create([
        'name' => $name, 'control_id' => $this->cash->control_id, 'subcategory_id' => $sub, 'type_id' => $this->cash->type_id, 'is_settlement' => $settlement,
    ]);

    $this->momo = $account('Momo A/C', $money->id, true);
    $this->bank = $account('Bank A/C', $money->id, true);
    $account('Loan Payable', $borrowings->id);
    $account('Stated Capital', $funds->id);

    (new LoanTemplateSeeder())->run();

    $this->loans = app(LoanService::class);

    $this->props = function ($user, string $url) {
        $props = null;

        $this->actingAs($user)->get($url)->assertOk()->assertInertia(function ($page) use (&$props) {
            $props = $page->toArray();
        });

        return $props;
    };

    $this->newLoan = fn(array $over = []) => array_merge([
        'kind' => 'money', 'lender_name' => 'Sika Microfinance', 'lender_type' => 'microfinance',
        'amount' => '500', 'date' => now()->toDateString(), 'paid_into' => $this->cash->id,
    ], $over);

    $this->form = fn(array $over = []) => array_merge([
        'kind' => 'money', 'lender_name' => 'Sika Microfinance', 'lender_type' => 'microfinance',
        'amount' => '500', 'date' => now()->toDateString(), 'paid_into' => $this->cash->id,
        'idempotency_key' => 'form-key-1',
    ], $over);
});

test('the loans page lists the farmer own loans with lender, amount, balance and status, newest first', function () {
    $this->loans->record($this->profile, $this->farmerUser, ($this->newLoan)(['lender_name' => 'First Lender', 'amount' => '200']));
    $second = $this->loans->record($this->profile, $this->farmerUser, ($this->newLoan)(['lender_name' => 'Second Lender', 'amount' => '300']));
    $this->loans->record($this->profile, $this->farmerUser, ['kind' => 'existing', 'lender_name' => 'Uncle Kofi', 'lender_type' => 'individual', 'amount' => '100']);

    $page = ($this->props)($this->farmerUser, '/loans');

    expect($page['component'])->toBe('Loans/Index')
        ->and(collect($page['props']['loans'])->map(fn($loan) => [$loan['lender'], $loan['amount'], $loan['balance'], $loan['status']])->all())
        ->toBe([['Uncle Kofi', 10000, 10000, 'active'], ['Second Lender', 30000, 30000, 'active'], ['First Lender', 20000, 20000, 'active']])
        ->and(collect($page['props']['loans'])->every(fn($loan) => isset($loan['uuid']) && ! array_key_exists('id', $loan)))->toBeTrue();
});

test('a cancelled loan stays in the list with balance zero and the cancelled status', function () {
    $loan = $this->loans->record($this->profile, $this->farmerUser, ($this->newLoan)());
    ($this->cancel)($loan->transaction);

    $row = ($this->props)($this->farmerUser, '/loans')['props']['loans'][0];

    expect([$row['status'], $row['amount'], $row['balance']])->toBe(['cancelled', 50000, 0]);
});

test('a farmer sees none of another farmer loans', function () {
    $this->loans->record($this->clean, $this->clean->user ?? $this->farmerUser, ($this->newLoan)(['lender_name' => 'Not Yours']));

    expect(($this->props)($this->farmerUser, '/loans')['props']['loans'])->toBe([]);
});

test('with no loans the page sends an empty list', function () {
    expect(($this->props)($this->farmerUser, '/loans')['props']['loans'])->toBe([]);
});

test('the status shown is Repaid only when a loan in force owes nothing', function () {
    $repaid = new Loan(['status' => 'active', 'principal_minor' => 0]);
    $cancelled = new Loan(['status' => 'cancelled', 'principal_minor' => 500]);
    $owing = new Loan(['status' => 'active', 'principal_minor' => 500]);

    expect([$repaid->displayStatus(), $cancelled->displayStatus(), $owing->displayStatus()])->toBe(['repaid', 'cancelled', 'active']);
});

test('the two forms open with their kind, the lender types, the paid-into choices and a fresh key', function () {
    $money = ($this->props)($this->farmerUser, '/loans/create');
    $existing = ($this->props)($this->farmerUser, '/loans/existing/create');

    expect([$money['component'], $money['props']['kind']])->toBe(['Loans/Create', 'money'])
        ->and([$existing['component'], $existing['props']['kind']])->toBe(['Loans/Create', 'existing'])
        ->and($money['props']['lenderTypes'])->toBe(['bank', 'microfinance', 'group', 'individual', 'other'])
        ->and(collect($money['props']['paidInto'])->pluck('label')->all())->toBe(['Cash', 'MoMo', 'Bank'])
        ->and($money['props']['idempotencyKey'])->not->toBeEmpty();
});

test('saving a money loan creates it for the signed-in farmer and goes back to the list', function () {
    $this->actingAs($this->farmerUser)->post('/loans', ($this->form)())->assertRedirect('/loans');

    $loan = Loan::where('farmer_profile_id', $this->profile->id)->first();

    expect([$loan->lender_name, $loan->principal_minor, $loan->status])->toBe(['Sika Microfinance', 50000, 'active']);
});

test('saving a loan I already had creates an opening entry', function () {
    $this->actingAs($this->farmerUser)->post('/loans', [
        'kind' => 'existing', 'lender_name' => 'Uncle Kofi', 'lender_type' => 'individual', 'amount' => '300',
        'interest' => '20', 'idempotency_key' => 'form-key-2',
    ])->assertRedirect('/loans');

    $loan = Loan::where('farmer_profile_id', $this->profile->id)->first();

    expect([$loan->kind, $loan->principal_minor, $loan->agreed_interest_minor, $loan->transaction->template->is_non_cash])
        ->toBe(['existing', 30000, 2000, true]);
});

test('an amount that is not above zero shows the approved words beside the amount field', function (string $amount) {
    $this->actingAs($this->farmerUser)->post('/loans', ($this->form)(['amount' => $amount]))
        ->assertSessionHasErrors(['amount' => 'Enter an amount above zero.']);

    expect(Loan::count())->toBe(0);
})->with(['0', '0.00', '-5', 'abc', '']);

test('a date in the future shows the approved words beside the date field', function () {
    $this->actingAs($this->farmerUser)->post('/loans', ($this->form)(['date' => now()->addDay()->toDateString()]))
        ->assertSessionHasErrors(['date' => 'This date is in the future.']);
});

test('a due date before the loan date is simply refused, with no text of its own', function () {
    $this->actingAs($this->farmerUser)->post('/loans', ($this->form)(['due_date' => now()->subDay()->toDateString()]))
        ->assertRedirect();

    // the refusal is there, with an empty message: nothing is said, the form just does not go through
    expect(data_get(json_decode(json_encode(session('errors')), true), 'default.messages.due_date'))->toBe(['']);

    expect(Loan::count())->toBe(0);
});

test('a negative interest, a missing lender or a lender type that is not on the list is refused', function () {
    foreach ([['interest' => '-1', 'f' => 'interest'], ['lender_name' => '', 'f' => 'lender_name'], ['lender_type' => 'cousin', 'f' => 'lender_type'], ['paid_into' => $this->receivable->id, 'f' => 'paid_into']] as $case) {
        $field = $case['f'];
        unset($case['f']);

        $this->actingAs($this->farmerUser)->post('/loans', ($this->form)($case))->assertSessionHasErrors($field);
    }

    expect(Loan::count())->toBe(0);
});

test('a posting refusal comes back with the existing text, and nothing is saved', function () {
    $this->actingAs($this->farmerUser)->post('/loans', ($this->form)(['date' => now()->subYears(5)->toDateString()]))
        ->assertSessionHas('error', 'There is no accounting period covering that date.');

    expect(Loan::count())->toBe(0);
});

test('sending the same form twice makes one loan', function () {
    $this->actingAs($this->farmerUser)->post('/loans', ($this->form)());
    $this->actingAs($this->farmerUser)->post('/loans', ($this->form)());

    expect(Loan::count())->toBe(1);
});

test('the dashboard shows the principal still owed on the active loans, and nothing from a cancelled one', function () {
    $this->loans->record($this->profile, $this->farmerUser, ($this->newLoan)(['amount' => '500']));
    $this->loans->record($this->profile, $this->farmerUser, ['kind' => 'existing', 'lender_name' => 'Uncle Kofi', 'lender_type' => 'individual', 'amount' => '300', 'interest' => '99']);
    $cancelled = $this->loans->record($this->profile, $this->farmerUser, ($this->newLoan)(['amount' => '90']));
    ($this->cancel)($cancelled->transaction);

    $summary = ($this->props)($this->farmerUser, '/farmer/dashboard')['props']['summary'];

    expect($summary['total_owed'])->toBe(80000);
});

test('the dashboard shows zero owed when there are no loans', function () {
    expect(($this->props)($this->farmerUser, '/farmer/dashboard')['props']['summary']['total_owed'])->toBe(0);
});

test('every other dashboard figure is unchanged by loans', function () {
    $before = ($this->props)($this->farmerUser, '/farmer/dashboard')['props']['summary'];

    $this->loans->record($this->profile, $this->farmerUser, ($this->newLoan)());

    $after = ($this->props)($this->farmerUser, '/farmer/dashboard')['props']['summary'];
    unset($before['total_owed'], $after['total_owed']);

    expect($after)->toBe($before);
});
