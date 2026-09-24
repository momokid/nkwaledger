<?php

namespace Database\Seeders;

use App\Models\ProductUnit;
use Illuminate\Database\Seeder;

class ProductUnitSeeder extends Seeder
{
    private const UNITS = ['Kg', 'Litre', 'Bag', 'Piece'];

    public function run(): void
    {
        foreach (self::UNITS as $name) {
            ProductUnit::firstOrCreate(['name' => $name]);
        }
    }
}
