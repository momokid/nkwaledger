<?php

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Needs a real PostgreSQL database whose name ends in _test (it is wiped afterwards).
// It cannot run inside the usual per-test transaction: two other processes must see the data.
function runChild(string $mode, User $user, FarmerProfile $profile, string $uuid, int $template, int $settlement, float $startAt)
{
    $command = [PHP_BINARY, base_path('tests/Support/concurrent-post.php'), $mode, (string) $user->id, $profile->uuid, $uuid, (string) $template, (string) $settlement, (string) $startAt];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), array_merge(getenv(), ['APP_ENV' => 'testing']));

    return [$process, $pipes];
}

function finishChild(array $child): array
{
    [$process, $pipes] = $child;
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    proc_close($process);

    return json_decode($out, true) ?? ['status' => 0, 'body' => $out . $err];
}

test('a web post and a sync post of the same record, at the same moment, leave one ledger entry', function () {
    if (DB::getDriverName() !== 'pgsql' || ! str_ends_with((string) DB::connection()->getDatabaseName(), '_test')) {
        $this->markTestSkipped('needs a PostgreSQL database whose name ends in _test');
    }

    // end the shared per-test transaction so what is written next is visible to the child processes
    DB::commit();

    try {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PermissionsSeeder::class);

        $dr = LedgerClass::create(['name' => 'Dr']);
        $cr = LedgerClass::create(['name' => 'Cr']);
        $assetSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Assets', 'class_id' => $dr->id])->id, 'name' => 'Money']);
        $incomeSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Income', 'class_id' => $cr->id])->id, 'name' => 'Farm Income']);
        $control = LedgerControl::create(['name' => 'General']);
        $type = LedgerType::create(['name' => 'GL']);
        $cash = LedgerAccount::create(['name' => 'Cash', 'control_id' => $control->id, 'subcategory_id' => $assetSub->id, 'type_id' => $type->id, 'is_settlement' => true]);
        $sales = LedgerAccount::create(['name' => 'Sales', 'control_id' => $control->id, 'subcategory_id' => $incomeSub->id, 'type_id' => $type->id]);
        $template = TransactionTemplate::create([
            'name' => 'I sold crops', 'slug' => 'crop_sale', 'transaction_type' => 'INCOME',
            'debit_account_id' => $cash->id, 'credit_account_id' => $sales->id, 'settlement_side' => 'debit',
        ]);
        AccountingPeriod::create(['name' => 'Test', 'starts_on' => now()->startOfYear()->toDateString(), 'ends_on' => now()->endOfYear()->toDateString()]);

        $user = User::factory()->create();
        $user->assignRole('farmer');
        $profile = FarmerProfile::factory()->create(['user_id' => $user->id]);

        $doubled = [];

        for ($round = 1; $round <= 20; $round++) {
            $uuid = (string) Str::uuid();
            $startAt = microtime(true) + 4;

            $children = [
                runChild('web', $user, $profile, $uuid, $template->id, $cash->id, $startAt),
                runChild('sync', $user, $profile, $uuid, $template->id, $cash->id, $startAt),
            ];
            $answers = array_map('finishChild', $children);

            expect(array_column($answers, 'status'))->each->toBe(200, "round {$round}: " . json_encode($answers));

            $entries = Transaction::whereIn('idempotency_key', [$uuid, "sync.{$user->id}.{$uuid}"])->count();

            if ($entries !== 1) {
                $doubled[] = $round;
            }
        }

        expect($doubled)->toBe([])
            ->and(JournalEntry::count())->toBe(20);
    } finally {
        Artisan::call('migrate:fresh', ['--force' => true]);
    }
});
