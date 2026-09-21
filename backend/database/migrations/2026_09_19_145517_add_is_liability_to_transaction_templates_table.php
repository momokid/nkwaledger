<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_templates', function (Blueprint $table) {
            // marks an income template as money still owed to someone else (e.g. a deposit
            // held for a buyer), so the statement's Money In splits it apart from real income
            $table->boolean('is_liability')->default(false)->after('is_stock_purchase');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_templates', function (Blueprint $table) {
            $table->dropColumn('is_liability');
        });
    }
};
