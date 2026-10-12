<?php

namespace Database\Seeders;

use App\Models\Lender;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

// the institutions a farmer can pick as a lender, from a file the owner supplies (columns: type, name, source; any row
// whose type is not a lender type is skipped). Safe to run again: a name already there is left exactly as it is, so an admin's correction survives
class InstitutionSeeder extends Seeder
{
    public function __construct(private readonly ?string $path = null) {}

    public function run(): void
    {
        $path = $this->path ?? database_path('seeders/data/institutions.csv');

        if (! is_file($path)) {
            throw new RuntimeException("The institution list is missing: {$path}. Provide the file, then run this again.");
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        if ($header === false || array_slice($header, 0, 2) !== ['type', 'name']) {
            throw new RuntimeException('The institution list must start with the columns type and name.');
        }

        DB::transaction(function () use ($handle) {
            while (($row = fgetcsv($handle)) !== false) {
                [$type, $name] = [trim((string) ($row[0] ?? '')), Lender::squish((string) ($row[1] ?? ''))];

                if ($name === '' || ! in_array($type, Lender::TYPES, true)) {
                    continue;
                }

                if (! Lender::where('name_key', Lender::keyOf($name))->exists()) {
                    (new Lender())->forceFill(['uuid' => (string) Str::uuid7(), 'type' => $type] + Lender::derived($name))->save();
                }
            }
        });

        fclose($handle);
    }
}
