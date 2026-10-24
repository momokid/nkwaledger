<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_units', function (Blueprint $table) {
            // what the browser sees, so a farm unit cannot be found by counting upward
            $table->uuid('uuid')->nullable()->after('id');
        });

        DB::table('farm_units')->whereNull('uuid')->orderBy('id')->each(function ($row) {
            DB::table('farm_units')->where('id', $row->id)->update(['uuid' => (string) Str::uuid7()]);
        });

        Schema::table('farm_units', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('farm_units', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
