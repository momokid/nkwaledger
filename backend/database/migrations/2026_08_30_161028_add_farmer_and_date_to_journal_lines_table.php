<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            // copied from the transaction at posting, so one farmer's books read without joining
            $table->foreignId('farmer_profile_id')
                ->after('ledger_account_id')
                ->constrained()
                ->restrictOnDelete();

            $table->date('transaction_date')->after('farmer_profile_id');

            // the shape of every statement and trial balance
            $table->index(['farmer_profile_id', 'transaction_date']);
            $table->index(['farmer_profile_id', 'ledger_account_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropIndex(['farmer_profile_id', 'ledger_account_id', 'transaction_date']);
            $table->dropIndex(['farmer_profile_id', 'transaction_date']);
            $table->dropConstrainedForeignId('farmer_profile_id');
            $table->dropColumn('transaction_date');
        });
    }
};
