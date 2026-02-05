<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();

            // Who initiated the transaction
            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // Business meaning
            $table->enum('type', ['income', 'expense', 'loss']);

            // Amount (single source of truth)
            $table->decimal('amount', 15, 2);

            // Business date (VERY important)
            $table->date('transaction_date');

            // Approval workflow
            $table->enum('status', ['pending', 'approved', 'rejected'])
                  ->default('pending');

            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()
                  ->references('id')->on('users');

            // Audit
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_transactions');
    }
};
