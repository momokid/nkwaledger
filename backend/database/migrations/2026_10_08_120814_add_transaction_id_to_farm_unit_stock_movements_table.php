<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_unit_stock_movements', function (Blueprint $table) {
            $table->foreignId('transaction_id')->nullable()->after('farm_unit_stock_id')
                ->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('farm_unit_stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transaction_id');
        });
    }
};
