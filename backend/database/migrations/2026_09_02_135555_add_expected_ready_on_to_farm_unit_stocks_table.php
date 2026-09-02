<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_unit_stocks', function (Blueprint $table) {
            $table->date('expected_ready_on')->nullable()->after('started_on');
        });
    }

    public function down(): void
    {
        Schema::table('farm_unit_stocks', function (Blueprint $table) {
            $table->dropColumn('expected_ready_on');
        });
    }
};
