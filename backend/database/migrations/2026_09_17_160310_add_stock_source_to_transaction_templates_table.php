<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_templates', function (Blueprint $table) {
            // only meaningful when is_stock_purchase is true - tells PostingService which
            // StockSource to stamp on the FarmUnitStock it auto-creates ("purchase" or
            // "opening_balance"), so one shared code path serves both declaration kinds
            $table->string('stock_source')->nullable()->after('is_stock_purchase');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_templates', function (Blueprint $table) {
            $table->dropColumn('stock_source');
        });
    }
};
