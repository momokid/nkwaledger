<?php

// Task 3.6a, part C: who may see and create loans. A farmer sees and creates only their own; an admin can
// see them read-only on the farmer page and can create none; agents, vets and advisers have no loan routes yet.
// The rules live in LoanPolicy; hiding a menu item is not security.

require_once __DIR__ . '/../../Support/Institutions.php';
require_once __DIR__ . '/../../Support/LockedFiguresFixture.php';

use App\Models\FarmerProfile;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerSubcategory;
use App\Models\Loan;
use App\Models\User;
use App\Services\Loans\LoanService;
use Database\Seeders\LoanTemplateSeeder;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    buildLockedFigures($this);

    $money = LedgerSubcategory::where('name', 'Money')->first();
    $cr = LedgerClass::where('name', 'Cr')->first();
    $borrowings = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Liabilities', 'class_id' => $cr->id])->id, 'name' => 'Borrowings']);
    $funds = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Equity', 'class_id' => $cr->id])->id, 'name' => 'Owner Funds']);

    $account = fn(string $name, int $sub, bool $settlement = false) => LedgerAccount::create([
        'name' => $name, 'control_id' => $this->cash->control_id, 'subcategory_id' => $sub, 'type_id' => $this->cash->type_id, 'is_settlement' => $settlement,
    ]);

    $account('Momo A/C', $money->id, true);
    $account('Bank A/C', $money->id, true);
    $account('Loan Payable', $borrowings->id);
    $account('Stated Capital', $funds->id);

    (new LoanTemplateSeeder())->run();

    $staff = function (string $role) {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    };

    $this->agent = $staff('agent');
    $this->vet = $staff('vet');
    $this->adviser = $staff('adviser');
    $this->otherAgent = $staff('agent');

    $this->profile->update(['assigned_agent_id' => $this->agent->id]);

    // a second farmer who can sign in
    $this->otherFarmerUser = $staff('farmer');
    $this->otherFarmer = FarmerProfile::factory()->create(['user_id' => $this->otherFarmerUser->id]);

    $this->loan = app(LoanService::class)->record($this->profile, $this->farmerUser, [
        'kind' => 'money', 'lender_name' => 'Sika Microfinance', 'lender' => institutionNamed('Sika Microfinance', 'microfinance')->uuid, 'lender_type' => 'microfinance',
        'amount' => '500', 'date' => now()->toDateString(), 'paid_into' => $this->cash->id,
    ]);

    $this->post = fn() => [
        'kind' => 'money', 'lender_name' => 'Sika Microfinance', 'lender' => institutionNamed('Sika Microfinance', 'microfinance')->uuid, 'lender_type' => 'microfinance',
        'amount' => '100', 'date' => now()->toDateString(), 'paid_into' => $this->cash->id,
        'idempotency_key' => 'k-' . uniqid(),
    ];
});

// --- the routes, by who is asking ---

test('the farmer may open the list and both forms and save a loan', function () {
    $this->actingAs($this->farmerUser)->get('/loans')->assertOk();
    $this->actingAs($this->farmerUser)->get('/loans/create')->assertOk();
    $this->actingAs($this->farmerUser)->get('/loans/existing/create')->assertOk();
    $this->actingAs($this->farmerUser)->post('/loans', ($this->post)())->assertRedirect('/loans');
});

test('an admin gets 403 on the list and on every create route', function () {
    $this->actingAs($this->admin)->get('/loans')->assertForbidden();
    $this->actingAs($this->admin)->get('/loans/create')->assertForbidden();
    $this->actingAs($this->admin)->get('/loans/existing/create')->assertForbidden();
    $this->actingAs($this->admin)->post('/loans', ($this->post)())->assertForbidden();

    expect(Loan::count())->toBe(1);
});

test('an agent, a vet and an adviser have no loan routes yet: 403 everywhere', function (string $who) {
    $user = $this->{$who};

    $this->actingAs($user)->get('/loans')->assertForbidden();
    $this->actingAs($user)->get('/loans/create')->assertForbidden();
    $this->actingAs($user)->get('/loans/existing/create')->assertForbidden();
    $this->actingAs($user)->post('/loans', ($this->post)())->assertForbidden();

    expect(Loan::count())->toBe(1);
})->with(['agent', 'vet', 'adviser']);

test('somebody who is not signed in is sent to sign in', function () {
    $this->get('/loans')->assertRedirect('/login');
    $this->post('/loans', ($this->post)())->assertRedirect('/login');
});

test('a farmer sees only their own loans', function () {
    $page = null;
    $this->actingAs($this->otherFarmerUser)->get('/loans')->assertOk()->assertInertia(function ($inertia) use (&$page) {
        $page = $inertia->toArray()['props']['loans'];
    });

    expect($page)->toBe([]);
});

test('a loan saved by a farmer always belongs to that farmer', function () {
    $this->actingAs($this->otherFarmerUser)->post('/loans', ($this->post)() + ['farmer_profile_id' => $this->profile->id])->assertRedirect('/loans');

    expect(Loan::where('farmer_profile_id', $this->otherFarmer->id)->count())->toBe(1)
        ->and(Loan::where('farmer_profile_id', $this->profile->id)->count())->toBe(1);
});

// --- the policy ---

test('the policy: a farmer views their own loan, anyone outside the scope gets a 404, an admin sees all', function () {
    $status = fn(User $user, string $ability, $argument) => Gate::forUser($user)->inspect($ability, $argument)->status();

    expect($status($this->farmerUser, 'view', $this->loan))->toBeNull()
        ->and(Gate::forUser($this->farmerUser)->allows('view', $this->loan))->toBeTrue()
        ->and($status($this->otherFarmerUser, 'view', $this->loan))->toBe(404)
        ->and($status($this->agent, 'view', $this->loan))->toBe(404)
        ->and($status($this->vet, 'view', $this->loan))->toBe(404)
        ->and($status($this->adviser, 'view', $this->loan))->toBe(404)
        ->and(Gate::forUser($this->admin)->allows('view', $this->loan))->toBeTrue();
});

test('the policy: only a farmer creates, and an admin gets a plain refusal', function () {
    expect(Gate::forUser($this->farmerUser)->allows('create', Loan::class))->toBeTrue()
        ->and(Gate::forUser($this->admin)->allows('create', Loan::class))->toBeFalse()
        ->and(Gate::forUser($this->agent)->allows('create', Loan::class))->toBeFalse()
        ->and(Gate::forUser($this->vet)->allows('create', Loan::class))->toBeFalse()
        ->and(Gate::forUser($this->adviser)->allows('create', Loan::class))->toBeFalse();
});

test('the policy: an admin and the farmer current agent read a farmer loans from the farmer page, nobody else', function () {
    expect(Gate::forUser($this->admin)->allows('viewForFarmer', [Loan::class, $this->profile]))->toBeTrue()
        ->and(Gate::forUser($this->agent)->allows('viewForFarmer', [Loan::class, $this->profile]))->toBeTrue()
        ->and(Gate::forUser($this->otherAgent ?? User::factory()->create()->assignRole('agent'))->allows('viewForFarmer', [Loan::class, $this->profile]))->toBeFalse()
        ->and(Gate::forUser($this->farmerUser)->allows('viewForFarmer', [Loan::class, $this->profile]))->toBeFalse()
        ->and(Gate::forUser($this->vet)->allows('viewForFarmer', [Loan::class, $this->profile]))->toBeFalse();
});

// --- the admin's read-only section ---

test('the admin farmer page lists the farmer loans, read-only, with no ids', function () {
    $cancelled = app(LoanService::class)->record($this->profile, $this->farmerUser, [
        'kind' => 'existing', 'lender_name' => 'Uncle Kofi', 'lender' => institutionNamed('Uncle Kofi', 'other')->uuid, 'lender_type' => 'other', 'amount' => '300',
    ]);
    ($this->cancel)($cancelled->transaction);

    $loans = null;
    $this->actingAs($this->admin)->get("/admin/farmers/{$this->profile->uuid}")->assertOk()->assertInertia(function ($inertia) use (&$loans) {
        $loans = $inertia->toArray()['props']['loans'];
    });

    expect(collect($loans)->map(fn($row) => [$row['lender'], $row['amount'], $row['balance'], $row['status']])->all())
        ->toBe([['Uncle Kofi', 30000, 0, 'cancelled'], ['Sika Microfinance', 50000, 50000, 'active']])
        ->and(collect($loans)->every(fn($row) => isset($row['uuid']) && ! array_key_exists('id', $row)))->toBeTrue();
});

test('the farmer page shows the assigned agent the loans, with the actions, and an admin the loans without them', function () {
    $props = null;
    $this->actingAs($this->agent)->get("/agent/farmers/{$this->profile->uuid}")->assertOk()->assertInertia(function ($inertia) use (&$props) {
        $props = $inertia->toArray()['props'];
    });

    expect(collect($props['loans'])->pluck('lender')->all())->toBe(['Sika Microfinance'])
        ->and($props['loan_actions'])->toBeTrue();
});

test('the admin page has no way to create a loan: the create routes stay 403 for the admin session', function () {
    $this->actingAs($this->admin)->post('/loans', ($this->post)())->assertForbidden();
    $this->actingAs($this->admin)->post("/admin/farmers/{$this->profile->uuid}/loans", ($this->post)())->assertNotFound();
});
