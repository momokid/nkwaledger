<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IndexAudit extends Command
{
    protected $signature = 'db:index-audit';

    protected $description = 'List foreign key columns with no index behind them';

    public function handle(): int
    {
        $missing = [];

        foreach ($this->tables() as $table) {
            $indexed = $this->indexedColumns($table);

            foreach ($this->foreignKeyColumns($table) as $column) {
                if (! in_array($column, $indexed, true)) {
                    $missing[] = [$table, $column];
                }
            }
        }

        if ($missing === []) {
            $this->info('Every foreign key has an index behind it.');

            return self::SUCCESS;
        }

        $this->warn(count($missing) . ' foreign key columns have no index:');
        $this->table(['Table', 'Column'], $missing);

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function tables(): array
    {
        return collect(Schema::getTables())
            ->pluck('name')
            ->reject(fn(string $name) => str_starts_with($name, 'pg_'))
            ->values()
            ->all();
    }

    // a column is covered when an index starts with it, not merely mentions it
    private function indexedColumns(string $table): array
    {
        return collect(Schema::getIndexes($table))
            ->map(fn(array $index) => $index['columns'][0] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function foreignKeyColumns(string $table): array
    {
        return collect(Schema::getForeignKeys($table))
            ->flatMap(fn(array $key) => $key['columns'])
            ->unique()
            ->values()
            ->all();
    }
}
