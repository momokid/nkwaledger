<?php

namespace App\Console\Commands;

use App\Support\Phone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalisePhones extends Command
{
    protected $signature = 'phones:normalise {--dry-run : Show what would change without writing}';

    protected $description = 'Rewrite stored phone numbers into the one spelling the app uses';

    public function handle(): int
    {
        $dryRun    = (bool) $this->option('dry-run');
        $converted = 0;
        $rejected  = [];
        $collided  = [];

        // rows written before normalisation existed hold whatever spelling they were created with
        DB::table('users')->select('id', 'phone')->orderBy('id')->each(
            function ($row) use ($dryRun, &$converted, &$rejected, &$collided) {
                $clean = Phone::normalise($row->phone);

                if ($clean === null) {
                    $rejected[] = "  {$row->id}  {$row->phone}";
                    return;
                }

                if ($clean === $row->phone) {
                    return;
                }

                // another row already holds the cleaned number, so writing this one would break the unique index
                $taken = DB::table('users')
                    ->where('phone', $clean)
                    ->where('id', '!=', $row->id)
                    ->exists();

                if ($taken) {
                    $collided[] = "  {$row->id}  {$row->phone}  duplicate of {$clean}";
                    return;
                }

                $this->line("  {$row->id}  {$row->phone}  ->  {$clean}");

                if (! $dryRun) {
                    DB::table('users')->where('id', $row->id)->update(['phone' => $clean]);
                }

                $converted++;
            }
        );

        $this->newLine();
        $this->info($dryRun ? "{$converted} would be converted." : "{$converted} converted.");

        // a number nobody can reach by sms is left as it is, for a person to correct
        if ($rejected !== []) {
            $this->newLine();
            $this->warn('Not a Ghanaian mobile, left untouched:');
            $this->line(implode(PHP_EOL, $rejected));
        }

        if ($collided !== []) {
            $this->newLine();
            $this->warn('Two accounts hold the same number, left untouched:');
            $this->line(implode(PHP_EOL, $collided));
        }

        return self::SUCCESS;
    }
}
