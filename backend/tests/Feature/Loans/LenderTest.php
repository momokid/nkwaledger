<?php

// Lenders are institutions only: a bank, a microfinance, a credit union or another institution, kept in one shared
// list that only an admin adds to. A loan links to a lender by public id and never carries a typed lender name.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Exceptions\Ledger\PostingFailed;
use App\Models\Lender;
use App\Models\Loan;
use App\Models\PendingEntry;
use App\Services\Loans\LenderService;
use App\Services\Loans\LoanService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    buildLoanWorld($this);

    $this->borrow = fn(array $over = [], $farmer = null, $by = null) => app(LoanService::class)->record($farmer ?? $this->profile, $by ?? $this->farmerUser, ($this->moneyEntry)($over));
    $this->search = fn($who, string $q, array $extra = []) => $this->actingAs($who)->postJson('/lenders/search', ['q' => $q] + $extra);
});

// --- search ---

test('search finds institutions by name, and shows only their public id, name and type', function () {
    Lender::create(['type' => 'bank', 'name' => 'Sikaman Bank']);
    Lender::create(['type' => 'credit_union', 'name' => 'Teachers Credit Union']);

    $found = ($this->search)($this->farmerUser, 'sika')->assertOk()->json();

    expect(collect($found)->pluck('name')->all())->toBe(['Sika Microfinance', 'Sikaman Bank'])
        ->and(array_keys($found[0]))->toEqualCanonicalizing(['uuid', 'name', 'type']);
});

test('search can be limited to one type', function () {
    Lender::create(['type' => 'bank', 'name' => 'Sikaman Bank']);

    $found = ($this->search)($this->farmerUser, 'sika', ['type' => 'bank'])->assertOk()->json();

    expect(collect($found)->pluck('name')->all())->toBe(['Sikaman Bank']);
});

test('search needs a login, two characters, and returns at most ten', function () {
    foreach (range(1, 15) as $n) {
        Lender::create(['type' => 'bank', 'name' => "Zeta Bank {$n}"]);
    }

    expect(($this->search)($this->farmerUser, 'ze')->assertOk()->json())->toHaveCount(10);
    ($this->search)($this->farmerUser, 'z')->assertStatus(422);
    ($this->search)($this->farmerUser, '')->assertStatus(422);

    Auth::forgetGuards();
    $this->postJson('/lenders/search', ['q' => 'ze'])->assertStatus(401);
});

test('search is rate limited', function () {
    $statuses = collect(range(1, 40))->map(fn() => ($this->search)($this->farmerUser, 'si')->status());

    expect($statuses->first())->toBe(200)->and($statuses->contains(429))->toBeTrue();
});

test('search is for farmers, agents and admins; a vet or adviser meets a 404', function () {
    ($this->search)($this->agent, 'si')->assertOk();
    ($this->search)($this->admin, 'si')->assertOk();
    ($this->search)($this->vet, 'si')->assertStatus(404);
    ($this->search)($this->adviser, 'si')->assertStatus(404);
});

test('search is a POST, so nothing typed lands in an address', function () {
    expect(\Illuminate\Support\Facades\Route::getRoutes()->match(\Illuminate\Http\Request::create('/lenders/search', 'POST'))->methods())->not->toContain('GET');
});

// --- picked, never typed ---

test('a lender is picked by its public id and the loan links to it', function () {
    $loan = ($this->borrow)();

    expect($loan->lender_id)->toBe($this->institution->id)
        ->and($loan->lender_name)->toBe('Sika Microfinance')
        ->and($loan->lender_type)->toBe('microfinance');
});

test('a typed name that was not picked from the list is refused, and nothing is made', function () {
    $count = Lender::count();

    foreach ([
        ['lender' => null, 'lender_name' => 'My Own Bank'],
        ['lender' => '', 'lender_name' => 'Sika Microfinance'],
        ['lender' => 'not-a-uuid', 'lender_name' => 'Sika Microfinance'],
        ['lender' => '00000000-0000-4000-8000-000000000000', 'lender_name' => 'Sika Microfinance'],
    ] as $input) {
        expect(fn() => app(LoanService::class)->record($this->profile, $this->farmerUser, array_merge(
            ['kind' => 'money', 'lender_type' => 'bank', 'amount' => '500', 'date' => now()->toDateString(), 'paid_into' => $this->cash->id],
            $input,
        )))->toThrow(PostingFailed::class, LenderService::NOT_IN_LIST);
    }

    expect(Lender::count())->toBe($count)->and(Loan::count())->toBe(0);
});

test('the lender picked must be of the type chosen', function () {
    expect(fn() => ($this->borrow)(['lender_type' => 'bank', 'lender' => $this->institution->uuid]))->toThrow(PostingFailed::class, LenderService::NOT_IN_LIST);
});

test('the name saved is the one in the list, whatever name was posted', function () {
    $loan = ($this->borrow)(['lender' => $this->institution->uuid, 'lender_name' => 'Something Else Typed']);

    expect($loan->lender_name)->toBe('Sika Microfinance');
});

test('a person or group is not a lender type any more', function () {
    expect(Loan::LENDER_TYPES)->toBe(['bank', 'microfinance', 'credit_union', 'other'])
        ->and(fn() => ($this->borrow)(['lender_type' => 'individual', 'lender' => $this->institution->uuid]))->toThrow(InvalidArgumentException::class);
});

test('a lender has no phone, no note and no phone-based identity anywhere', function () {
    expect(Schema::getColumnListing('lenders'))->toEqualCanonicalizing(['id', 'uuid', 'type', 'name', 'name_key', 'created_by', 'created_at', 'updated_at']);

    $entry = $this->entries->recordLoan($this->profile, $this->agent, ($this->moneyEntry)());

    expect(array_keys($entry->payload))->not->toContain('lender_phone')->and(array_keys($entry->payload))->not->toContain('lender_note');
});

// --- sharing, and who sees what ---

test('two farmers using the same lender share one record and cannot see each other\'s loans', function () {
    $mine = ($this->borrow)();
    $theirs = ($this->borrow)([], $this->otherFarmer, $this->otherFarmerUser);

    expect($theirs->lender_id)->toBe($mine->lender_id)->and(Lender::where('name', 'Sika Microfinance')->count())->toBe(1);

    $list = null;
    $this->actingAs($this->farmerUser)->get('/loans')->assertOk()->assertInertia(function ($page) use (&$list) {
        $list = $page->toArray()['props']['loans'];
    });

    expect(collect($list)->pluck('uuid')->all())->toBe([$mine->uuid]);
    $this->actingAs($this->farmerUser)->get("/loans/{$theirs->uuid}")->assertStatus(404);
});

test('the lender name shows on the loan list, loan page and Reports page, CSV and print', function () {
    $loan = ($this->borrow)();
    $range = "from={$this->from}&to={$this->to}";

    $csv = $this->actingAs($this->farmerUser)->get("/my-reports/csv?{$range}")->assertOk()->getContent();
    $print = $this->actingAs($this->farmerUser)->get("/my-reports/print?{$range}")->assertOk()->getContent();
    $page = json_encode($this->actingAs($this->farmerUser)->get("/my-reports?{$range}")->assertOk()->viewData('page')['props']);
    $list = json_encode($this->actingAs($this->farmerUser)->get('/loans')->assertOk()->viewData('page')['props']['loans']);
    $detail = json_encode($this->actingAs($this->farmerUser)->get("/loans/{$loan->uuid}")->assertOk()->viewData('page')['props']['loan']);

    foreach ([$csv, $print, $page, $list, $detail] as $output) {
        expect($output)->toContain('Sika Microfinance');
    }

    expect($detail)->not->toContain('phone');
});

test('a vet or an adviser sees no lender data, and an agent who is not assigned meets a 404', function () {
    $loan = ($this->borrow)();

    $this->actingAs($this->vet)->get("/loans/{$loan->uuid}")->assertStatus(404);
    $this->actingAs($this->adviser)->get("/loans/{$loan->uuid}")->assertStatus(404);
    $this->actingAs($this->otherAgent)->get("/agent/loans/{$loan->uuid}/repayments/create")->assertStatus(404);
    $this->actingAs($this->vet)->get('/admin/lenders')->assertStatus(403);
});

// --- agent-recorded loans ---

test('an agent\'s entry follows the institution rule when recorded and again when accepted', function () {
    // at entry: an unpicked name stores nothing
    expect(fn() => $this->entries->recordLoan($this->profile, $this->agent, ($this->moneyEntry)(['lender' => '', 'lender_name' => 'Typed Only'])))
        ->toThrow(PostingFailed::class, LenderService::NOT_IN_LIST)
        ->and(PendingEntry::count())->toBe(0);

    $entry = $this->entries->recordLoan($this->profile, $this->agent, ($this->moneyEntry)(['lender_name' => 'Gone Bank', 'lender_type' => 'bank']));
    expect(Lender::where('name', 'Gone Bank')->exists())->toBeTrue();

    // at acceptance the lender is looked up again; it is gone, so the entry is refused and stays waiting
    Lender::where('name', 'Gone Bank')->delete();

    expect(fn() => $this->entries->accept($entry, $this->farmerUser, 'app'))->toThrow(PostingFailed::class, LenderService::NOT_IN_LIST)
        ->and($entry->fresh()->status)->toBe('pending')
        ->and(Loan::count())->toBe(0);
});

test('an accepted agent entry links to the shared lender', function () {
    $entry = $this->entries->recordLoan($this->profile, $this->agent, ($this->moneyEntry)());
    $loan = $this->entries->accept($entry, $this->farmerUser, 'app');

    expect($loan->lender_id)->toBe($this->institution->id);
});

// --- the seeder ---

test('the institution seeder runs twice with no duplicates, skips any row that is not an institution, and never invents names', function () {
    $path = tempnam(sys_get_temp_dir(), 'inst');
    file_put_contents($path, "type,name,source\nbank,\"Alpha Bank\",x\nmicrofinance,\"Beta Micro\",x\ncredit_union,\"Gamma Union\",x\nbank,\"alpha  bank\",x\nindividual,\"Some Person\",x\n");

    (new \Database\Seeders\InstitutionSeeder($path))->run();
    (new \Database\Seeders\InstitutionSeeder($path))->run();

    expect(Lender::whereIn('name', ['Alpha Bank', 'Beta Micro', 'Gamma Union'])->count())->toBe(3)
        ->and(Lender::where('name', 'Some Person')->exists())->toBeFalse();

    expect(fn() => (new \Database\Seeders\InstitutionSeeder('/no/such/file.csv'))->run())->toThrow(RuntimeException::class);
});

test('the seeder works with model events off, as it does under the database seeder', function () {
    $path = tempnam(sys_get_temp_dir(), 'inst');
    file_put_contents($path, "type,name,source\nbank,\"Gamma Bank\",x\n");

    Lender::withoutEvents(fn() => (new \Database\Seeders\InstitutionSeeder($path))->run());

    $lender = Lender::where('name', 'Gamma Bank')->first();

    expect($lender->uuid)->not->toBeEmpty()->and($lender->name_key)->toBe('gamma bank');
});

test('the real institution file seeds cleanly twice', function () {
    (new \Database\Seeders\InstitutionSeeder())->run();
    $count = Lender::count();
    (new \Database\Seeders\InstitutionSeeder())->run();

    expect(Lender::count())->toBe($count)->and($count)->toBeGreaterThan(300);
});
