<?php

// Only an admin adds institutions, corrects a name or changes a type, and every change is audit-logged. Nobody else can.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\AuditLog;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Lender;
use App\Models\Loan;
use App\Models\Transaction;
use App\Services\Loans\LenderService;
use Database\Seeders\InstitutionSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    buildLoanWorld($this);

    $this->actors = ['admin' => $this->admin, 'farmer' => $this->farmerUser, 'agent' => $this->agent, 'vet' => $this->vet, 'adviser' => $this->adviser];
    $this->logs = fn(Lender $lender, string $action = 'lender.updated') => AuditLog::where('auditable_type', Lender::class)->where('auditable_id', $lender->id)->where('action', $action)->get();
    $this->patch = fn(Lender $lender, array $body, $who = null) => $this->actingAs($who ?? $this->admin)->patchJson("/admin/lenders/{$lender->uuid}", $body);
});

test('only an admin may open the lenders page, add an institution, correct a name or change a type', function (string $who, int $page, int $add, int $rename, int $type) {
    $lender = Lender::create(['type' => 'bank', 'name' => 'Some Bank']);
    $user = $this->actors[$who];

    $this->actingAs($user)->get('/admin/lenders')->assertStatus($page);
    $this->actingAs($user)->postJson('/admin/lenders', ['type' => 'bank', 'name' => "New Bank {$who}"])->assertStatus($add);
    $this->actingAs($user)->patchJson("/admin/lenders/{$lender->uuid}", ['name' => 'Renamed Bank'])->assertStatus($rename);
    $this->actingAs($user)->patchJson("/admin/lenders/{$lender->uuid}", ['type' => 'other'])->assertStatus($type);
})->with([
    'admin' => ['admin', 200, 201, 200, 200],
    'farmer' => ['farmer', 403, 403, 403, 403],
    'agent' => ['agent', 403, 403, 403, 403],
    'vet' => ['vet', 403, 403, 403, 403],
    'adviser' => ['adviser', 403, 403, 403, 403],
]);

test('an admin adds an institution of any of the five types, it is audit-logged and searchable', function (string $type) {
    $this->actingAs($this->admin)->postJson('/admin/lenders', ['type' => $type, 'name' => '  Zeta   Place '])->assertCreated()->assertJsonPath('name', 'Zeta Place');

    $lender = Lender::where('name', 'Zeta Place')->first();
    $log = ($this->logs)($lender, 'lender.created')->first();

    expect($lender->type)->toBe($type)
        ->and($log->action)->toBe('lender.created')->and((int) $log->user_id)->toBe($this->admin->id)
        ->and($this->actingAs($this->farmerUser)->postJson('/lenders/search', ['q' => 'zeta'])->json())->toHaveCount(1);
})->with(['bank', 'rural_bank', 'credit_financial_institution', 'microfinance', 'other']);

test('a name must be new, ignoring case and extra spaces, and the type must be one of the five', function () {
    $this->actingAs($this->admin)->postJson('/admin/lenders', ['type' => 'bank', 'name' => 'sika  MICROFINANCE'])->assertStatus(422);
    $this->actingAs($this->admin)->postJson('/admin/lenders', ['type' => 'individual', 'name' => 'Some Person'])->assertStatus(422);
    $this->actingAs($this->admin)->postJson('/admin/lenders', ['type' => 'credit_union', 'name' => 'Some Union'])->assertStatus(422);
    $this->actingAs($this->admin)->postJson('/admin/lenders', ['type' => 'bank', 'name' => '   '])->assertStatus(422);

    expect(Lender::count())->toBe(1);
});

test('an admin corrects a name, the change is audit-logged with before and after, and a clash is refused', function () {
    $bank = Lender::create(['type' => 'bank', 'name' => 'Old Name Bank']);

    ($this->patch)($bank, ['name' => 'New Name Bank'])->assertOk();

    $log = ($this->logs)($bank)->first();

    expect($bank->fresh()->name)->toBe('New Name Bank')
        ->and($log->old_values)->toBe(['name' => 'Old Name Bank'])->and($log->new_values)->toBe(['name' => 'New Name Bank']);

    ($this->patch)($this->institution, ['name' => 'Sika Microfinance'])->assertOk();
    ($this->patch)($this->institution, ['name' => 'new  name  BANK'])->assertStatus(422);
});

test('an admin changes a lender\'s type to each of the five', function (string $type) {
    $lender = Lender::create(['type' => $type === 'bank' ? 'other' : 'bank', 'name' => 'Movable Place']);

    ($this->patch)($lender, ['type' => $type])->assertOk()->assertJsonPath('type', $type)->assertJsonPath('name', 'Movable Place');

    expect($lender->fresh()->type)->toBe($type);
})->with(['bank', 'rural_bank', 'credit_financial_institution', 'microfinance', 'other']);

test('a type outside the five is refused and nothing changes', function (string $type) {
    $lender = Lender::create(['type' => 'bank', 'name' => 'Steady Bank']);
    $before = $lender->fresh()->toJson();

    ($this->patch)($lender, ['type' => $type])->assertStatus(422)->assertJsonValidationErrors(['type']);

    expect($lender->fresh()->toJson())->toBe($before)->and(($this->logs)($lender)->count())->toBe(0);
})->with(['credit_union', 'individual', 'BANK', 'x']);

test('a farmer or an agent cannot change a type, and no row changes', function (string $who) {
    $lender = Lender::create(['type' => 'bank', 'name' => 'Steady Bank']);
    $before = $lender->fresh()->toJson();

    ($this->patch)($lender, ['type' => 'other'], $this->actors[$who])->assertStatus(403);

    expect($lender->fresh()->toJson())->toBe($before)->and(AuditLog::where('action', 'lender.updated')->count())->toBe(0);
})->with(['farmer', 'agent']);

test('a type change is audit-logged with only the old and new type', function () {
    $lender = Lender::create(['type' => 'bank', 'name' => 'Logged Bank']);

    ($this->patch)($lender, ['type' => 'rural_bank'])->assertOk();

    $log = ($this->logs)($lender)->sole();

    expect($log->old_values)->toBe(['type' => 'bank'])->and($log->new_values)->toBe(['type' => 'rural_bank'])
        ->and((int) $log->user_id)->toBe($this->admin->id);
});

test('a type edit leaves the name, name_key, every loan and the ledger untouched', function () {
    $loan = ($this->openLoan)();
    $lender = $loan->lender;
    $tables = [(new Transaction())->getTable(), (new JournalEntry())->getTable(), (new JournalLine())->getTable(), (new Loan())->getTable()];
    $snapshot = fn() => array_map(fn($table) => DB::table($table)->orderBy('id')->get()->toJson(), $tables);

    $before = ['name' => $lender->name, 'name_key' => $lender->name_key, 'tables' => $snapshot()];

    ($this->patch)($lender, ['type' => 'credit_financial_institution'])->assertOk();

    $after = $lender->fresh();

    expect($after->type)->toBe('credit_financial_institution')
        ->and([$after->name, $after->name_key])->toBe([$before['name'], $before['name_key']])
        ->and($snapshot())->toBe($before['tables']);
});

test('a type-only save does not rewrite the name or name_key', function () {
    DB::table('lenders')->where('id', $this->institution->id)->update(['name' => 'Odd  Spacing Place', 'name_key' => 'kept as stored']);

    ($this->patch)($this->institution, ['type' => 'other'])->assertOk();

    expect($this->institution->fresh()->only(['name', 'name_key', 'type']))->toBe(['name' => 'Odd  Spacing Place', 'name_key' => 'kept as stored', 'type' => 'other']);
});

test('a rename alone and a type edit alone both work, and each leaves the other as it was', function () {
    $lender = Lender::create(['type' => 'bank', 'name' => 'Both Ways']);

    ($this->patch)($lender, ['name' => 'Both Ways Renamed'])->assertOk();
    expect($lender->fresh()->only(['name', 'type']))->toBe(['name' => 'Both Ways Renamed', 'type' => 'bank']);

    ($this->patch)($lender, ['type' => 'microfinance'])->assertOk();
    expect($lender->fresh()->only(['name', 'type']))->toBe(['name' => 'Both Ways Renamed', 'type' => 'microfinance']);

    ($this->patch)($lender, ['name' => 'Both At Once', 'type' => 'other'])->assertOk();

    $logs = ($this->logs)($lender)->sortBy('id')->values();

    expect($lender->fresh()->only(['name', 'type']))->toBe(['name' => 'Both At Once', 'type' => 'other'])
        ->and($logs->map(fn($l) => array_keys($l->new_values))->all())->toBe([['name'], ['type'], ['name', 'type']]);
});

test('a name clash refuses a combined edit and the type is not changed either', function () {
    $lender = Lender::create(['type' => 'bank', 'name' => 'Clashing Bank']);

    ($this->patch)($lender, ['name' => 'Sika Microfinance', 'type' => 'other'])->assertStatus(422);

    expect($lender->fresh()->only(['name', 'type']))->toBe(['name' => 'Clashing Bank', 'type' => 'bank'])->and(($this->logs)($lender)->count())->toBe(0);
});

test('saving the same name or type writes no audit entry, and an empty edit is refused', function () {
    $lender = Lender::create(['type' => 'bank', 'name' => 'Same Bank']);

    ($this->patch)($lender, ['name' => 'Same Bank', 'type' => 'bank'])->assertOk();
    ($this->patch)($lender, [])->assertStatus(422);

    expect(($this->logs)($lender)->count())->toBe(0);
});

test('two admins editing from the same old copy get the true before and after', function () {
    $lender = Lender::create(['type' => 'bank', 'name' => 'Raced Bank']);
    $first = Lender::find($lender->id);
    $second = Lender::find($lender->id);
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    app(LenderService::class)->update($first, null, 'rural_bank', $this->admin);
    app(LenderService::class)->update($second, null, 'microfinance', $this->admin);

    $logs = ($this->logs)($lender)->sortBy('id')->values();

    expect($logs->map(fn($l) => [$l->old_values['type'], $l->new_values['type']])->all())->toBe([['bank', 'rural_bank'], ['rural_bank', 'microfinance']])
        ->and($lender->fresh()->type)->toBe('microfinance');

    if (DB::getDriverName() === 'pgsql') {
        expect(collect($queries)->contains(fn($sql) => str_contains(strtolower($sql), 'for update')))->toBeTrue();
    }
});

test('the seeder does not undo an admin\'s type edit on the next run', function () {
    $path = tempnam(sys_get_temp_dir(), 'inst');
    file_put_contents($path, "type,name,source\nbank,\"Fixable Bank\",community bank\n");
    (new InstitutionSeeder($path))->run();

    $lender = Lender::where('name', 'Fixable Bank')->first();
    expect($lender->type)->toBe('rural_bank');

    ($this->patch)($lender, ['type' => 'credit_financial_institution'])->assertOk();
    (new InstitutionSeeder($path))->run();

    expect($lender->fresh()->type)->toBe('credit_financial_institution')->and(Lender::where('name', 'Fixable Bank')->count())->toBe(1);
});

test('the admin page lists type and name with no id of a row and no phone, and offers the five types', function () {
    $props = null;
    $this->actingAs($this->admin)->get('/admin/lenders')->assertOk()->assertInertia(function ($page) use (&$props) {
        $props = $page->toArray()['props'];
    });

    expect(array_keys($props['lenders'][0]))->toEqualCanonicalizing(['uuid', 'type', 'name'])
        ->and($props['types'])->toBe(['bank', 'rural_bank', 'credit_financial_institution', 'microfinance', 'other']);
});

// --- accent twins: names that fold to the same key are one lender ---

test('adding an accent twin of an existing name is refused with the existing duplicate key', function () {
    Lender::create(['type' => 'bank', 'name' => 'Societe Generale']);

    $this->actingAs($this->admin)->postJson('/admin/lenders', ['type' => 'bank', 'name' => 'Société Générale'])
        ->assertStatus(422)->assertJsonValidationErrors(['name'])->assertJsonPath('errors.name.0', 'lender.institution_exists');

    expect(Lender::where('sort_key', 'societe generale')->count())->toBe(1);
});

test('renaming into the accent twin of another lender is refused and nothing changes', function () {
    Lender::create(['type' => 'bank', 'name' => 'Societe Generale']);
    $other = Lender::create(['type' => 'bank', 'name' => 'Other Place']);
    $before = $other->fresh()->toJson();

    ($this->patch)($other, ['name' => 'Société Générale'])->assertStatus(422)->assertJsonPath('errors.name.0', 'lender.institution_exists');

    expect($other->fresh()->toJson())->toBe($before)->and(($this->logs)($other)->count())->toBe(0);
});

test('a lender may be renamed to an accent variant of itself, and keeps its id and loans', function () {
    $lender = Lender::create(['type' => 'bank', 'name' => 'Societe']);
    $loan = ($this->openLoan)(['lender_name' => 'Societe', 'lender' => $lender->uuid]);
    $id = $lender->id;

    ($this->patch)($lender, ['name' => 'Société'])->assertOk()->assertJsonPath('name', 'Société');

    $after = $lender->fresh();

    expect([$after->id, $after->name, $after->name_key, $after->sort_key])->toBe([$id, 'Société', 'société', 'societe'])
        ->and($loan->fresh()->lender_id)->toBe($id);
});

test('a clash that only the unique index sees gives the same duplicate error', function () {
    $fired = false;
    Lender::creating(function () use (&$fired) {
        if (! $fired) {
            $fired = true;
            DB::table('lenders')->insert(['uuid' => (string) \Illuminate\Support\Str::uuid7(), 'type' => 'bank', 'name' => 'Societe', 'name_key' => 'societe', 'sort_key' => 'societe', 'created_at' => now(), 'updated_at' => now()]);
        }
    });

    $this->actingAs($this->admin)->postJson('/admin/lenders', ['type' => 'bank', 'name' => 'Société'])
        ->assertStatus(422)->assertJsonPath('errors.name.0', 'lender.institution_exists');

    expect(Lender::where('name', 'Société')->exists())->toBeFalse();
});

test('a name with a character that cannot be folded to plain letters is refused on add and on rename', function (string $name) {
    $lender = Lender::create(['type' => 'bank', 'name' => 'Plain Bank']);

    $this->actingAs($this->admin)->postJson('/admin/lenders', ['type' => 'bank', 'name' => $name])
        ->assertStatus(422)->assertJsonPath('errors.name.0', 'lender.name_characters');
    ($this->patch)($lender, ['name' => $name])->assertStatus(422)->assertJsonPath('errors.name.0', 'lender.name_characters');

    expect($lender->fresh()->name)->toBe('Plain Bank')->and(Lender::count())->toBe(2);
})->with(['open e' => 'Ɛ Rural Bank', 'open o' => 'Bank ɔ', 'chinese' => '名前 Bank', 'emoji' => 'Cafe ☕']);

test('a rename updates sort_key and a type edit does not change it', function () {
    $lender = Lender::create(['type' => 'bank', 'name' => 'Cafe Bank']);

    ($this->patch)($lender, ['name' => 'Café Rural'])->assertOk();
    expect($lender->fresh()->only(['name_key', 'sort_key']))->toBe(['name_key' => 'café rural', 'sort_key' => 'cafe rural']);

    DB::table('lenders')->where('id', $lender->id)->update(['sort_key' => 'kept as stored']);
    ($this->patch)($lender, ['type' => 'other'])->assertOk();

    expect($lender->fresh()->only(['name', 'name_key', 'sort_key', 'type']))->toBe(['name' => 'Café Rural', 'name_key' => 'café rural', 'sort_key' => 'kept as stored', 'type' => 'other']);
});
