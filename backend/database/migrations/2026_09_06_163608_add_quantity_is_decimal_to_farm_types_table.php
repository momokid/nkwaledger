<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_types', function (Blueprint $table) {
            // false for headcount types (goats, birds); true for area/weight types (maize in acres, cassava in kg)
            $table->boolean('quantity_is_decimal')->default(false)->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('farm_types', function (Blueprint $table) {
            $table->dropColumn('quantity_is_decimal');
        });
    }
};
