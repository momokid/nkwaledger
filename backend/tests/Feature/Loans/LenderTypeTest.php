<?php

// Five lender types. The source column of the institution file sorts the list once (a migration); after that the
// seeder only adds names that are missing and never changes a row.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\Lender;
use App\Models\Loan;
use App\Models\PendingEntry;
use Database\Seeders\InstitutionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

const FIVE_TYPES = ['bank', 'rural_bank', 'credit_financial_institution', 'microfinance', 'other'];

// the migration that moves the list to five types, loaded from its file
function fiveTypesMigration(): object
{
    return require glob(database_path('migrations/*_move_lenders_to_five_types.php'))[0];
}

function runFiveTypes(): string
{
    ob_start();
    fiveTypesMigration()->up();

    return (string) ob_get_clean();
}

// the institution file as source => names
function institutionFile(): array
{
    $rows = [];
    $handle = fopen(database_path('seeders/data/institutions.csv'), 'r');
    fgetcsv($handle);

    while (($row = fgetcsv($handle)) !== false) {
        $rows[$row[2]][] = $row[1];
    }

    fclose($handle);

    return $rows;
}

// a lender row written the way the old list held it
function oldLender(string $name, string $type): void
{
    DB::table('lenders')->insert([
        'uuid' => (string) Str::uuid7(), 'type' => $type, 'name' => $name, 'name_key' => Lender::keyOf($name), 'sort_key' => Lender::sortKeyOf($name),
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function seedFile(string $csv): string
{
    $path = tempnam(sys_get_temp_dir(), 'inst');
    file_put_contents($path, $csv);

    return $path;
}

// --- the file and the seeder ---

test('the institution file has 23, 147, 26 and 131 rows per source, 327 in all', function () {
    $file = institutionFile();

    expect(array_map('count', $file))->toEqual([
        'commercial bank' => 23, 'community bank' => 147, 'savings and loans' => 26, 'deposit-taking microfinance' => 131,
    ])->and(array_sum(array_map('count', $file)))->toBe(327);
});

test('the types are the five keys, in this order', function () {
    expect(Lender::TYPES)->toBe(FIVE_TYPES)->and(Loan::LENDER_TYPES)->toBe(FIVE_TYPES);
});

test('the seeder sorts every institution by its source', function () {
    (new InstitutionSeeder())->run();

    expect(Lender::pluck('type')->countBy()->all())->toEqual(['bank' => 23, 'rural_bank' => 147, 'credit_financial_institution' => 26, 'microfinance' => 131]);

    foreach (['commercial bank' => 'bank', 'community bank' => 'rural_bank', 'savings and loans' => 'credit_financial_institution', 'deposit-taking microfinance' => 'microfinance'] as $source => $type) {
        expect(Lender::whereIn('name_key', array_map(fn($n) => Lender::keyOf($n), institutionFile()[$source]))->where('type', '!=', $type)->count())->toBe(0);
    }
});

test('no seeded institution is Other', function () {
    (new InstitutionSeeder())->run();

    expect(Lender::where('type', 'other')->count())->toBe(0);
});

test('the seeder is safe to run again: the same 327 rows, no duplicates', function () {
    (new InstitutionSeeder())->run();
    (new InstitutionSeeder())->run();

    expect(Lender::count())->toBe(327)->and(Lender::distinct('name_key')->count('name_key'))->toBe(327);
});

test('the seeder only adds missing names and never changes the type or name of an existing row', function () {
    $kept = Lender::create(['type' => 'other', 'name' => 'Alpha Bank']);
    $renamed = Lender::create(['type' => 'bank', 'name' => 'Beta Micro Renamed']);
    $before = [$kept->fresh()->only(['uuid', 'type', 'name', 'name_key']), $renamed->fresh()->only(['uuid', 'type', 'name', 'name_key'])];

    $path = seedFile("type,name,source\nbank,\"Alpha Bank\",commercial bank\nmicrofinance,\"Beta Micro\",deposit-taking microfinance\nbank,\"Gamma Rural\",community bank\n");
    (new InstitutionSeeder($path))->run();

    expect([$kept->fresh()->only(['uuid', 'type', 'name', 'name_key']), $renamed->fresh()->only(['uuid', 'type', 'name', 'name_key'])])->toBe($before)
        ->and(Lender::where('name', 'Gamma Rural')->value('type'))->toBe('rural_bank')
        ->and(Lender::where('name', 'Beta Micro')->value('type'))->toBe('microfinance');
});

test('a source the seeder does not know stops the run and writes nothing', function () {
    $path = seedFile("type,name,source\nbank,\"Alpha Bank\",commercial bank\nbank,\"Odd One\",credit union\n");

    expect(fn() => (new InstitutionSeeder($path))->run())->toThrow(RuntimeException::class)
        ->and(Lender::count())->toBe(0);
});

test('the seeder works with model events off, as it does under the database seeder', function () {
    $path = seedFile("type,name,source\nbank,\"Gamma Bank\",commercial bank\n");

    Lender::withoutEvents(fn() => (new InstitutionSeeder($path))->run());

    $lender = Lender::where('name', 'Gamma Bank')->first();

    expect($lender->uuid)->not->toBeEmpty()->and($lender->name_key)->toBe('gamma bank')->and($lender->type)->toBe('bank');
});

test('the seeder stops when the file is missing', function () {
    expect(fn() => (new InstitutionSeeder('/no/such/file.csv'))->run())->toThrow(RuntimeException::class);
});

// --- the one-time move ---

test('every old value is mapped, an unknown one becomes Other, and the count and values are printed', function () {
    foreach (['bank' => 'Old Bank', 'microfinance' => 'Old Micro', 'credit_union' => 'Old Union', 'other' => 'Old Other', 'cousin' => 'Odd One', 'group' => 'Odd Two'] as $type => $name) {
        oldLender($name, $type);
    }

    $printed = runFiveTypes();

    expect(DB::table('lenders')->pluck('type', 'name')->all())->toEqual([
        'Old Bank' => 'bank', 'Old Micro' => 'microfinance', 'Old Union' => 'credit_financial_institution', 'Old Other' => 'other',
        'Odd One' => 'other', 'Odd Two' => 'other',
    ])->and($printed)->toContain('cousin')->and($printed)->toContain('group');
});

test('the move sorts listed institutions by their source and leaves unlisted ones on their mapped type', function () {
    $file = institutionFile();

    oldLender($file['commercial bank'][0], 'bank');
    oldLender($file['community bank'][0], 'bank');
    oldLender($file['savings and loans'][0], 'microfinance');
    oldLender($file['deposit-taking microfinance'][0], 'microfinance');
    oldLender('Added By An Admin', 'bank');
    oldLender('Added Union', 'credit_union');

    runFiveTypes();

    expect(DB::table('lenders')->where('name', $file['commercial bank'][0])->value('type'))->toBe('bank')
        ->and(DB::table('lenders')->where('name', $file['community bank'][0])->value('type'))->toBe('rural_bank')
        ->and(DB::table('lenders')->where('name', $file['savings and loans'][0])->value('type'))->toBe('credit_financial_institution')
        ->and(DB::table('lenders')->where('name', $file['deposit-taking microfinance'][0])->value('type'))->toBe('microfinance')
        ->and(DB::table('lenders')->where('name', 'Added By An Admin')->value('type'))->toBe('bank')
        ->and(DB::table('lenders')->where('name', 'Added Union')->value('type'))->toBe('credit_financial_institution');
});

test('the move never changes a name or a public id', function () {
    oldLender(institutionFile()['community bank'][0], 'bank');
    $before = DB::table('lenders')->first(['uuid', 'name', 'name_key']);

    runFiveTypes();

    expect(DB::table('lenders')->first(['uuid', 'name', 'name_key']))->toEqual($before);
});

test('an old value never survives the move', function () {
    foreach (array_merge(['bank', 'microfinance', 'credit_union', 'other', 'cousin'], array_fill(0, 3, 'bank')) as $i => $type) {
        oldLender("Row {$i}", $type);
    }

    runFiveTypes();

    expect(DB::table('lenders')->whereNotIn('type', FIVE_TYPES)->count())->toBe(0);
});

test('the move runs once: a second deploy changes nothing', function () {
    oldLender('Old Union', 'credit_union');
    Artisan::call('migrate', ['--force' => true]);

    expect(DB::table('migrations')->where('migration', 'like', '%move_lenders_to_five_types')->count())->toBe(1);

    // told to run by hand a second time, it finds nothing left to change and says nothing
    runFiveTypes();
    $snapshot = DB::table('lenders')->orderBy('id')->get()->toJson();
    $second = runFiveTypes();

    expect(DB::table('lenders')->orderBy('id')->get()->toJson())->toBe($snapshot)->and($second)->toBe('');
});

test('the move takes lender_type out of the payloads of waiting entries only', function () {
    buildLoanWorld($this);

    $old = ['lender_type' => 'bank', 'lender_name' => 'Sika Microfinance'];
    $waiting = ($this->pendingMoney)();
    $accepted = ($this->pendingMoney)();
    $declined = ($this->pendingMoney)();
    $withdrawn = ($this->pendingMoney)();

    foreach ([$waiting, $accepted, $declined, $withdrawn] as $entry) {
        DB::table('pending_entries')->where('id', $entry->id)->update(['payload' => json_encode($entry->payload + $old)]);
    }

    DB::table('pending_entries')->where('id', $accepted->id)->update(['status' => 'accepted']);
    DB::table('pending_entries')->where('id', $declined->id)->update(['status' => 'declined']);
    DB::table('pending_entries')->where('id', $withdrawn->id)->update(['status' => 'withdrawn']);

    $others = fn() => DB::table('pending_entries')->whereIn('id', [$accepted->id, $declined->id, $withdrawn->id])->orderBy('id')->pluck('payload')->all();
    $untouched = $others();

    runFiveTypes();

    $keys = fn(PendingEntry $entry) => array_keys($entry->fresh()->payload);

    expect($keys($waiting))->not->toContain('lender_type')->and($keys($waiting))->toContain('lender')
        ->and($keys($waiting))->toContain('lender_name')
        ->and($others())->toBe($untouched);

    $once = $waiting->fresh()->payload;
    runFiveTypes();

    expect($waiting->fresh()->payload)->toBe($once);
});

test('an old waiting entry still accepts after the move', function () {
    buildLoanWorld($this);

    DB::table('lenders')->where('id', $this->institution->id)->update(['type' => 'credit_union']);
    $entry = ($this->pendingMoney)();
    DB::table('pending_entries')->where('id', $entry->id)->update(['payload' => json_encode($entry->payload + ['lender_type' => 'credit_union', 'lender_name' => 'Sika Microfinance'])]);

    runFiveTypes();

    $loan = $this->entries->accept($entry->fresh(), $this->farmerUser, 'app');

    expect($loan->lender_id)->toBe($this->institution->id)
        ->and($this->institution->fresh()->type)->toBe('credit_financial_institution')
        ->and($entry->fresh()->status)->toBe('accepted');
});

// --- the column ---

test('the column holds the longest type key', function () {
    $lender = Lender::create(['type' => 'credit_financial_institution', 'name' => 'Long Type Place']);

    expect(Lender::find($lender->id)->type)->toBe('credit_financial_institution');

    if (DB::getDriverName() === 'pgsql') {
        expect((int) DB::selectOne("select character_maximum_length as n from information_schema.columns where table_name = 'lenders' and column_name = 'type'")->n)->toBe(40);
    }
});

// --- accent twins and the seeder ---

test('the seeder skips a name whose accent twin is already there, reports it, and never fails the run', function () {
    Lender::create(['type' => 'bank', 'name' => 'Societe']);

    $path = seedFile("type,name,source\nbank,\"Société\",commercial bank\nbank,\"Alpha Zed\",commercial bank\nbank,\"Alpha Zéd\",commercial bank\nbank,\"Plain Bank\",commercial bank\n");
    $seeder = new InstitutionSeeder($path);
    $seeder->run();

    expect($seeder->skipped)->toBe(['Société', 'Alpha Zéd'])
        ->and(Lender::orderBy('name')->pluck('name')->all())->toBe(['Alpha Zed', 'Plain Bank', 'Societe']);

    $again = new InstitutionSeeder($path);
    $again->run();

    expect($again->skipped)->toBe(['Société', 'Alpha Zéd']);
});

test('a normal re-run of the seeder reports nothing', function () {
    $path = seedFile("type,name,source\nbank,\"Alpha Bank\",commercial bank\n");
    (new InstitutionSeeder($path))->run();

    $again = new InstitutionSeeder($path);
    $again->run();

    expect($again->skipped)->toBe([]);
});
