<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ledger_transactions', function (Blueprint $table) {
            $table->boolean("is_reversal")->default(false)->after("status");

            $table->foreignId("reverses_transaction_id")->nullable()->after("is_reversal")->nullableOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ledger_transactions', function (Blueprint $table) {
            $table->dropForeign(['reverses_transaction_id']);
            $table->dropColumn(['is_reversal', 'reverses_transaction_id']);
        });
    }
};
