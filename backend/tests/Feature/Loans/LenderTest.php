<?php

// Lenders are institutions only, kept in one shared list that only an admin adds to. A loan links to a lender by
// public id: no typed name and no type from the browser is ever saved or trusted.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Exceptions\Ledger\PostingFailed;
use App\Models\Lender;
use App\Models\Loan;
use App\Models\PendingEntry;
use App\Services\Loans\LenderService;
use App\Services\Loans\LoanListService;
use App\Services\Loans\LoanService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    buildLoanWorld($this);

    $this->borrow = fn(array $over = [], $farmer = null, $by = null) => app(LoanService::class)->record($farmer ?? $this->profile, $by ?? $this->farmerUser, ($this->moneyEntry)($over));
    $this->search = fn($who, string $q, array $extra = []) => $this->actingAs($who)->postJson('/lenders/search', ['q' => $q] + $extra);
    $this->postLoan = fn(array $over = []) => $this->actingAs($this->farmerUser)->post('/loans', array_merge([
        'kind' => 'money', 'lender' => $this->institution->uuid, 'amount' => '500', 'date' => now()->toDateString(), 'paid_into' => $this->cash->id,
    ], $over));
});

// --- search ---

test('search finds institutions by name, and shows only their public id, name and type', function () {
    Lender::create(['type' => 'bank', 'name' => 'Sikaman Bank']);
    Lender::create(['type' => 'credit_financial_institution', 'name' => 'Teachers Savings and Loans']);

    $found = ($this->search)($this->farmerUser, 'sika')->assertOk()->json();

    expect(collect($found)->pluck('name')->all())->toBe(['Sika Microfinance', 'Sikaman Bank'])
        ->and(array_keys($found[0]))->toEqualCanonicalizing(['uuid', 'name', 'type']);
});

test('search covers all five types, by name only', function () {
    foreach (['bank', 'rural_bank', 'credit_financial_institution', 'microfinance', 'other'] as $type) {
        Lender::create(['type' => $type, 'name' => "Zeta {$type} place"]);
    }

    $found = ($this->search)($this->farmerUser, 'zeta')->assertOk()->json();

    expect(collect($found)->pluck('type')->sort()->values()->all())->toBe(['bank', 'credit_financial_institution', 'microfinance', 'other', 'rural_bank']);
});

test('search ignores a type sent by the browser', function () {
    Lender::create(['type' => 'bank', 'name' => 'Sikaman Bank']);

    $plain = ($this->search)($this->farmerUser, 'sika')->assertOk()->json();
    $sent = ($this->search)($this->farmerUser, 'sika', ['type' => 'bank'])->assertOk()->json();
    $bogus = ($this->search)($this->farmerUser, 'sika', ['type' => 'cousin'])->assertOk()->json();

    expect($sent)->toBe($plain)->and($bogus)->toBe($plain);
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
            ['kind' => 'money', 'amount' => '500', 'date' => now()->toDateString(), 'paid_into' => $this->cash->id],
            $input,
        )))->toThrow(PostingFailed::class, LenderService::NOT_IN_LIST);
    }

    expect(Lender::count())->toBe($count)->and(Loan::count())->toBe(0);
});

test('a name typed beside a picked lender is never saved or used, and the name comes from the list', function () {
    $count = Lender::count();

    ($this->postLoan)(['lender_name' => 'Something Else Typed'])->assertRedirect('/loans');

    $loan = Loan::firstOrFail();

    expect($loan->lender_name)->toBe('Sika Microfinance')->and(Lender::count())->toBe($count)
        ->and(Lender::where('name', 'Something Else Typed')->exists())->toBeFalse();
});

test('a typed name that matches another lender does not find that lender', function () {
    $other = Lender::create(['type' => 'bank', 'name' => 'Other Bank']);

    ($this->postLoan)(['lender_name' => 'Other Bank'])->assertRedirect('/loans');

    expect(Loan::firstOrFail()->lender_id)->toBe($this->institution->id)->and(Loan::where('lender_id', $other->id)->count())->toBe(0);
});

test('a type sent by the browser is ignored: the type is read from the lender', function () {
    ($this->postLoan)(['lender_type' => 'bank'])->assertRedirect('/loans');
    ($this->postLoan)(['lender_type' => 'cousin'])->assertRedirect('/loans');

    expect(Loan::count())->toBe(2)->and(Loan::all()->pluck('lender_type')->unique()->all())->toBe(['microfinance'])
        ->and(Lender::find($this->institution->id)->type)->toBe('microfinance');
});

test('a loan form needs only the lender id', function () {
    $this->actingAs($this->farmerUser)->post('/loans', [
        'kind' => 'money', 'lender' => $this->institution->uuid, 'amount' => '500', 'date' => now()->toDateString(), 'paid_into' => $this->cash->id,
    ])->assertSessionHasNoErrors()->assertRedirect('/loans');

    expect(Loan::count())->toBe(1);
});

test('the types are the five, and a person or group is not one', function () {
    expect(Loan::LENDER_TYPES)->toBe(['bank', 'rural_bank', 'credit_financial_institution', 'microfinance', 'other']);
});

test('a lender has no phone, no note and no phone-based identity anywhere', function () {
    expect(Schema::getColumnListing('lenders'))->toEqualCanonicalizing(['id', 'uuid', 'type', 'name', 'name_key', 'sort_key', 'created_by', 'created_at', 'updated_at']);

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

    // lenders have no delete in the app, but the database allows it for a lender no loan uses: the entry is refused and stays waiting
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

test('a new pending entry keeps the lender id and no type and no name', function () {
    $loanEntry = $this->entries->recordLoan($this->profile, $this->agent, array_merge(($this->moneyEntry)(), ['lender_type' => 'bank', 'lender_name' => 'Typed Name']));
    $repaymentEntry = $this->entries->recordRepayment(($this->openLoan)(), $this->agent, ($this->repayForm)('100'));

    expect($loanEntry->payload)->toHaveKey('lender')->not->toHaveKey('lender_type')->not->toHaveKey('lender_name')
        ->and($repaymentEntry->payload)->not->toHaveKey('lender_name')->not->toHaveKey('lender_type');
});

test('accepting reads the lender\'s type from the lender at that moment', function () {
    $entry = $this->entries->recordLoan($this->profile, $this->agent, ($this->moneyEntry)());
    DB::table('lenders')->where('id', $this->institution->id)->update(['type' => 'rural_bank']);

    $loan = $this->entries->accept($entry, $this->farmerUser, 'app');

    expect($loan->lender_type)->toBe('rural_bank');
});

test('pending cards, the staff list and the sms read the lender name from the lender, not from the payload', function () {
    $loanEntry = $this->entries->recordLoan($this->profile, $this->agent, ($this->moneyEntry)());
    $repaymentEntry = $this->entries->recordRepayment(($this->openLoan)(), $this->agent, ($this->repayForm)('100'));

    // an older waiting entry that still holds the name it was saved with
    DB::table('pending_entries')->where('id', $loanEntry->id)->update(['payload' => json_encode($loanEntry->payload + ['lender_name' => 'Stale Name'])]);
    DB::table('pending_entries')->where('id', $repaymentEntry->id)->update(['payload' => json_encode($repaymentEntry->payload + ['lender_name' => 'Stale Name'])]);
    DB::table('lenders')->where('id', $this->institution->id)->update(['name' => 'Sika Renamed', 'name_key' => 'sika renamed']);

    $lists = app(LoanListService::class);
    $cards = $lists->pendingFor($this->profile)->pluck('lender')->unique()->all();
    $rows = $lists->forStaff($this->profile)->where('row', 'entry')->pluck('lender')->unique()->all();

    $this->codes->send($loanEntry->fresh(), $this->agent);
    $sent = app(\App\Contracts\SmsProvider::class)->sent;
    $sms = end($sent)['message'];

    expect($cards)->toBe(['Sika Renamed'])->and($rows)->toBe(['Sika Renamed'])
        ->and($sms)->toContain('from Sika Renamed')->and($sms)->not->toContain('Stale Name');
});
