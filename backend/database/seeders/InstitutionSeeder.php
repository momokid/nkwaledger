<?php

namespace Database\Seeders;

use App\Models\Lender;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

// the institutions a farmer can pick as a lender, from a file the owner supplies (columns: type, name, source). The
// source decides the type. Safe to run again: it only adds a name that is missing and never changes a row that is
// there, so an admin's correction to a name or type survives
class InstitutionSeeder extends Seeder
{
    public const SOURCE_TYPES = [
        'commercial bank' => 'bank',
        'community bank' => 'rural_bank',
        'savings and loans' => 'credit_financial_institution',
        'deposit-taking microfinance' => 'microfinance',
    ];

    public function __construct(private readonly ?string $path = null) {}

    public function run(): void
    {
        $path = $this->path ?? database_path('seeders/data/institutions.csv');

        if (! is_file($path)) {
            throw new RuntimeException("The institution list is missing: {$path}. Provide the file, then run this again.");
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        if ($header === false || array_slice($header, 0, 3) !== ['type', 'name', 'source']) {
            throw new RuntimeException('The institution list must start with the columns type, name and source.');
        }

        try {
            DB::transaction(function () use ($handle) {
                while (($row = fgetcsv($handle)) !== false) {
                    $name = Lender::squish((string) ($row[1] ?? ''));

                    if ($name === '') {
                        continue;
                    }

                    $source = mb_strtolower(trim((string) ($row[2] ?? '')));
                    $type = self::SOURCE_TYPES[$source] ?? throw new RuntimeException("The institution list has a source this seeder does not know: \"{$source}\" for \"{$name}\".");

                    if (! Lender::where('name_key', Lender::keyOf($name))->exists()) {
                        (new Lender())->forceFill(['uuid' => (string) Str::uuid7(), 'type' => $type] + Lender::derived($name))->save();
                    }
                }
            });
        } finally {
            fclose($handle);
        }
    }
}
