<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_unit_stock_movements', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('farm_unit_stock_movements', function (Blueprint $table) {
            $table->dropColumn('cancelled_at');
        });
    }
};
