<?php

// The search finds and orders lenders by sort_key: lower case, squished, accents folded to plain letters, compared by
// bytes. So the order is the same on SQLite and PostgreSQL, whatever the database's own collation does with spaces.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\Lender;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    buildLoanWorld($this);

    $this->make = function (string ...$names) {
        foreach ($names as $name) {
            Lender::create(['type' => 'bank', 'name' => $name]);
        }
    };
    $this->found = fn(string $q) => $this->actingAs($this->farmerUser)->postJson('/lenders/search', ['q' => $q])->assertOk()->json();
    $this->names = fn(string $q) => collect(($this->found)($q))->pluck('name')->all();
});

// --- the order ---

test('Sika Microfinance comes before Sikaman Bank', function () {
    ($this->make)('Sikaman Bank');

    expect(($this->names)('sika'))->toBe(['Sika Microfinance', 'Sikaman Bank']);
});

test('names that start with the typed text come first, then the rest A to Z', function () {
    ($this->make)('First Sika Rural Bank', 'Bank of Sika', 'Sikaman Bank', 'Zed Sika');

    expect(($this->names)('sika'))->toBe(['Sika Microfinance', 'Sikaman Bank', 'Bank of Sika', 'First Sika Rural Bank', 'Zed Sika']);
});

test('the order of tied results is stable', function () {
    ($this->make)('Tie Bank B', 'Tie Bank A', 'Tie Bank C');

    $first = ($this->names)('tie bank');

    expect($first)->toBe(['Tie Bank A', 'Tie Bank B', 'Tie Bank C'])->and(($this->names)('tie bank'))->toBe($first)->and(($this->names)('TIE   BANK'))->toBe($first);
});

test('spaces and punctuation sort by their bytes, and the typed text is tidied the same way', function () {
    ($this->make)('Sika.Rural', 'Sika-Rural', 'Sika Rural', 'Sika & Co');

    $expected = ['Sika & Co', 'Sika Microfinance', 'Sika Rural', 'Sika-Rural', 'Sika.Rural'];

    expect(($this->names)('sika'))->toBe($expected)->and(($this->names)('  SIKA '))->toBe($expected);
});

test('the limit of ten cuts after the ordering', function () {
    foreach (range(1, 11) as $n) {
        ($this->make)(sprintf('A Zed Place %02d', $n));
    }

    ($this->make)('Zed Third', 'Zed First', 'Zed Second');

    expect(($this->names)('zed'))->toBe([
        'Zed First', 'Zed Second', 'Zed Third',
        'A Zed Place 01', 'A Zed Place 02', 'A Zed Place 03', 'A Zed Place 04', 'A Zed Place 05', 'A Zed Place 06', 'A Zed Place 07',
    ]);
});

// --- accents ---

test('typing e finds é, and typing é finds e', function () {
    ($this->make)('Café Rural Bank', 'Cafe Plain Bank');

    $both = ['Cafe Plain Bank', 'Café Rural Bank'];

    expect(($this->names)('cafe'))->toBe($both)->and(($this->names)('café'))->toBe($both)->and(($this->names)('CAFÉ'))->toBe($both);
});

test('an accented name sorts among plain letters', function () {
    ($this->make)('Écurie Bank', 'Ecobank Rural', 'Edge Bank');

    expect(($this->names)('ec'))->toBe(['Ecobank Rural', 'Écurie Bank']);
});

test('the folding keeps name and name_key as typed and gives the same key to accent twins', function () {
    expect(Lender::sortKeyOf('  Société   Générale '))->toBe('societe generale')
        ->and(Lender::sortKeyOf('Societe Generale'))->toBe('societe generale')
        ->and(Lender::sortKeyOf('Straße'))->toBe('strasse');

    $lender = Lender::create(['type' => 'bank', 'name' => 'Société  Générale']);

    expect($lender->only(['name', 'name_key', 'sort_key']))->toBe(['name' => 'Société Générale', 'name_key' => 'société générale', 'sort_key' => 'societe generale']);
});

test('the letters ɛ ɔ Ɛ Ɔ do not fold to plain letters, so a name with them counts as unfoldable', function () {
    foreach (['ɛ', 'ɔ', 'Ɛ', 'Ɔ'] as $letter) {
        expect(Lender::hasUnfoldable("Bank {$letter}"))->toBeTrue();
    }

    expect(Lender::hasUnfoldable('Société Générale'))->toBeFalse()->and(Lender::hasUnfoldable('Plain Bank'))->toBeFalse();
});

// --- wildcards ---

test('typing % or _ does not match everything', function () {
    ($this->make)('Alpha Bank', 'Beta_Bank', 'Gamma 100% Rural');

    expect(($this->names)('%%'))->toBe([])
        ->and(($this->names)('__'))->toBe([])
        ->and(($this->names)('a_'))->toBe(['Beta_Bank'])
        ->and(($this->names)('0%'))->toBe(['Gamma 100% Rural'])
        ->and(($this->names)('\x'))->toBe([]);
});

// --- the stored key ---

test('the database refuses two lenders with the same sort_key', function () {
    ($this->make)('Societe');

    expect(fn() => DB::table('lenders')->insert([
        'uuid' => (string) Str::uuid7(), 'type' => 'bank', 'name' => 'Société', 'name_key' => 'société', 'sort_key' => 'societe', 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('the order equals a plain byte sort of the keys (PostgreSQL)', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('needs PostgreSQL');
    }

    ($this->make)('Sika Rural', 'Sika-Rural', 'Sika.Rural', 'Sika & Co', 'Sikaman Bank', 'Bank of Sika', 'First Sika', 'Sika  Zed', 'Sikä Bank', 'Sika_Bank');

    $keys = Lender::where('sort_key', 'like', '%sika%')->pluck('sort_key')->all();
    usort($keys, fn($a, $b) => [! str_starts_with($a, 'sika'), $a] <=> [! str_starts_with($b, 'sika'), $b]);
    $expected = array_slice($keys, 0, 10);

    $got = collect(($this->found)('sika'))->map(fn($row) => Lender::where('uuid', $row['uuid'])->value('sort_key'))->all();

    expect($got)->toBe($expected);
});

// --- the backfill ---

function sortKeyMigration(): object
{
    return require glob(database_path('migrations/*_add_sort_key_to_lenders_table.php'))[0];
}

function runSortKeyMigration(): string
{
    ob_start();

    try {
        sortKeyMigration()->up();
    } finally {
        $out = (string) ob_get_clean();
    }

    return $out;
}

// the table as it was before the migration: no sort_key column
function withoutSortKey(): void
{
    DB::table('lenders')->delete();
    Schema::table('lenders', fn($table) => $table->dropUnique(['sort_key']));
    Schema::table('lenders', fn($table) => $table->dropColumn('sort_key'));
}

function plainLender(string $name): void
{
    DB::table('lenders')->insert(['uuid' => (string) Str::uuid7(), 'type' => 'bank', 'name' => $name, 'name_key' => Lender::keyOf($name), 'created_at' => now(), 'updated_at' => now()]);
}

test('the backfill gives every row a key, changes no name or name_key, lists rows it could not fully fold, and runs once', function () {
    withoutSortKey();
    foreach (['Plain Bank', 'Société Générale', 'Ɛ Rural Bank', 'Café ☕'] as $name) {
        plainLender($name);
    }
    $before = DB::table('lenders')->orderBy('id')->get(['name', 'name_key'])->toJson();

    $printed = runSortKeyMigration();

    expect(Schema::hasColumn('lenders', 'sort_key'))->toBeTrue()
        ->and(DB::table('lenders')->orderBy('id')->get(['name', 'name_key'])->toJson())->toBe($before)
        ->and(DB::table('lenders')->orderBy('id')->pluck('sort_key')->all())->toBe(['plain bank', 'societe generale', 'ɛ rural bank', 'cafe ☕'])
        ->and($printed)->toContain('Ɛ Rural Bank')->and($printed)->toContain('Café ☕')->and($printed)->not->toContain('Plain Bank');

    $again = runSortKeyMigration();

    expect($again)->toBe('')->and(DB::table('lenders')->orderBy('id')->pluck('sort_key')->all())->toBe(['plain bank', 'societe generale', 'ɛ rural bank', 'cafe ☕']);
    Artisan::call('migrate', ['--force' => true]);
    expect(DB::table('migrations')->where('migration', 'like', '%add_sort_key_to_lenders_table')->count())->toBe(1);
});

test('the backfill with no clash passes, including an empty table', function () {
    withoutSortKey();

    expect(runSortKeyMigration())->toBe('')->and(Schema::hasColumn('lenders', 'sort_key'))->toBeTrue();
});

test('the backfill aborts on a clash, lists the pair and changes nothing', function () {
    withoutSortKey();
    plainLender('Societe');
    plainLender('Société');
    plainLender('Other Bank');
    $before = DB::table('lenders')->orderBy('id')->get()->toJson();

    $message = '';

    try {
        runSortKeyMigration();
    } catch (RuntimeException $failure) {
        $message = $failure->getMessage();
    }

    expect($message)->toContain('Societe')->toContain('Société')->not->toContain('Other Bank')
        ->and(Schema::hasColumn('lenders', 'sort_key'))->toBeFalse()
        ->and(DB::table('lenders')->orderBy('id')->get()->toJson())->toBe($before);
});
