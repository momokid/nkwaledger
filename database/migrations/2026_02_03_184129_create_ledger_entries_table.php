<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ledger_transaction_id')
                  ->constrained('ledger_transactions')
                  ->cascadeOnDelete();

            $table->foreignId('account_id')
                  ->constrained('accounts')
                  ->restrictOnDelete();

            $table->enum('entry_type', ['debit', 'credit']);

            $table->decimal('amount', 15, 2);

            $table->timestamps();

            // Prevent duplicate postings
            $table->unique(
                ['ledger_transaction_id', 'account_id', 'entry_type'],
                'unique_ledger_entry'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
