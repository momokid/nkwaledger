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
        Schema::table('sync_submissions', function (Blueprint $table) {
            $table->uuid('uuid')->nullable();
        });

        // rows that already exist each get their own
        DB::table('sync_submissions')->whereNull('uuid')->orderBy('id')->each(
            fn($row) => DB::table('sync_submissions')->where('id', $row->id)->update(['uuid' => (string) Str::uuid7()]),
        );

        Schema::table('sync_submissions', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('sync_submissions', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
        });

        Schema::table('sync_submissions', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
