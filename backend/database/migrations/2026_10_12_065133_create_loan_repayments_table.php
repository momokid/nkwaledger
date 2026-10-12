<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // one row per repayment: the amount the farmer paid and the two records it became. Never edited
        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farmer_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('principal_minor');
            $table->unsignedBigInteger('interest_minor');
            // the LOAN record for the principal part; empty only when the principal part is 0
            $table->foreignId('principal_transaction_id')->nullable()->unique()->constrained('transactions')->nullOnDelete();
            // the EXPENSE record for the interest part; empty when the interest part is 0
            $table->foreignId('interest_transaction_id')->nullable()->unique()->constrained('transactions')->nullOnDelete();
            $table->foreignId('paid_from_account_id')->nullable()->constrained('ledger_accounts')->nullOnDelete();
            $table->date('repaid_on');
            // the farmer said yes to an amount above what was still owed
            $table->boolean('excess_confirmed')->default(false);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            // app or code, for an agent's entry the farmer accepted. Empty for a repayment the farmer entered
            $table->string('accepted_via', 10)->nullable();
            $table->string('idempotency_key', 64)->nullable();
            $table->timestamps();

            $table->index(['loan_id']);
            $table->unique(['farmer_profile_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_repayments');
    }
};
