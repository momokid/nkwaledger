<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The one-time move to five lender types. It runs once per database (the migrations table sees to that), so the
// institution file sorts the list here and the seeder never has to change a type again.
return new class extends Migration
{
    private const FIVE = ['bank', 'rural_bank', 'credit_financial_institution', 'microfinance', 'other'];

    private const OLD_TO_NEW = ['credit_union' => 'credit_financial_institution'];

    private const SOURCES = [
        'commercial bank' => 'bank',
        'community bank' => 'rural_bank',
        'savings and loans' => 'credit_financial_institution',
        'deposit-taking microfinance' => 'microfinance',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            $moved = $this->moveOldValues();
            [$sorted, $unlisted] = $this->sortBySource();
            $stripped = $this->stripWaitingPayloads();

            if ($moved === [] && $sorted === 0 && $stripped === 0) {
                return;
            }

            $list = implode(', ', array_map(fn($old, $count) => "{$old} ({$count})", array_keys($moved), $moved));

            echo "Lender types: old values moved: " . ($list === '' ? 'none' : $list) . ". Re-sorted by the institution list: {$sorted}. "
                . "Not in the list, kept as they are: {$unlisted}. Waiting entries cleaned: {$stripped}." . PHP_EOL;
        });
    }

    // the old four types are not restored
    public function down(): void {}

    /** @return array<string, int> each old value moved and how many rows it had; an unknown value becomes other */
    private function moveOldValues(): array
    {
        $moved = [];

        foreach (DB::table('lenders')->whereNotIn('type', self::FIVE)->distinct()->pluck('type') as $old) {
            $moved[$old] = DB::table('lenders')->where('type', $old)->update([
                'type' => self::OLD_TO_NEW[$old] ?? 'other',
                'updated_at' => now(),
            ]);
        }

        return $moved;
    }

    /** @return array{0: int, 1: int} rows given a new type, and rows whose name is not in the file */
    private function sortBySource(): array
    {
        $total = DB::table('lenders')->count();

        if ($total === 0) {
            return [0, 0];
        }

        $path = database_path('seeders/data/institutions.csv');

        if (! is_file($path)) {
            throw new RuntimeException("The institution list is missing: {$path}. Provide the file, then run the migration again.");
        }

        $handle = fopen($path, 'r');
        fgetcsv($handle);
        $sorted = 0;
        $listed = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $name = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) ($row[1] ?? ''))));
            $source = mb_strtolower(trim((string) ($row[2] ?? '')));

            if ($name === '') {
                continue;
            }

            $type = self::SOURCES[$source] ?? throw new RuntimeException("The institution list has a source this migration does not know: \"{$source}\" for \"{$name}\".");

            $listed += DB::table('lenders')->where('name_key', $name)->count();
            $sorted += DB::table('lenders')->where('name_key', $name)->where('type', '!=', $type)->update(['type' => $type, 'updated_at' => now()]);
        }

        fclose($handle);

        return [$sorted, $total - $listed];
    }

    // a waiting entry reads the lender's type from the lender now, so the old copy in its payload is dropped
    private function stripWaitingPayloads(): int
    {
        $stripped = 0;

        foreach (DB::table('pending_entries')->where('status', 'pending')->get(['id', 'payload']) as $entry) {
            $payload = json_decode($entry->payload, true);

            if (! is_array($payload) || ! array_key_exists('lender_type', $payload)) {
                continue;
            }

            unset($payload['lender_type']);
            DB::table('pending_entries')->where('id', $entry->id)->update(['payload' => json_encode($payload)]);
            $stripped++;
        }

        return $stripped;
    }
};
