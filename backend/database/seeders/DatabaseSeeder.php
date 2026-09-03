<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            // roles first, since permissions are granted to them
            RolesAndPermissionsSeeder::class,
            PermissionsSeeder::class,
            // categories before templates, since templates match on category name
            FarmTypeSeeder::class,
            // accounts first, since the templates look them up by name
            LedgerAccountSeeder::class,
            TransactionTemplateSeeder::class,
        ]);
    }
}
