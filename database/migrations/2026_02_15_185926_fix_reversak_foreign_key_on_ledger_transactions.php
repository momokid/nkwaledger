<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_transactions', function (Blueprint $table) {

            // Add foreign key properly
            $table->foreign('reverses_transaction_id')
                ->references('id')
                ->on('ledger_transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ledger_transactions', function (Blueprint $table) {
            $table->dropForeign(['reverses_transaction_id']);
        });
    }
};
