<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disease_reports', function (Blueprint $table) {
            $table->timestamp('responded_at')->nullable();
        });

        // reports answered before this column existed: the last update is the best date we have
        DB::table('disease_reports')->whereNotNull('response_note')->update(['responded_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('disease_reports', function (Blueprint $table) {
            $table->dropColumn('responded_at');
        });
    }
};
