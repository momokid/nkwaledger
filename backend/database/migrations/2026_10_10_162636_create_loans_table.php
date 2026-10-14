<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('farmer_profile_id')->constrained()->cascadeOnDelete();
            // money: received in cash, MoMo or bank; existing: a loan the farmer already had
            $table->string('kind', 20);
            // active or cancelled. Waiting, declined and withdrawn live on the pending entry, not here
            $table->string('status', 20)->default('active');
            $table->string('lender_name');
            $table->string('lender_type', 20);
            $table->unsignedBigInteger('principal_minor');
            $table->unsignedBigInteger('agreed_interest_minor')->nullable();
            $table->date('loan_date');
            $table->date('due_date')->nullable();
            $table->text('note')->nullable();
            // the account the money was paid into - money loans only
            $table->foreignId('settlement_account_id')->nullable()->constrained('ledger_accounts')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->unique()->constrained('transactions')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            // the form's own key, so sending it twice makes one loan
            $table->string('idempotency_key', 64)->nullable();
            $table->timestamps();

            $table->index(['farmer_profile_id', 'status']);
            $table->unique(['farmer_profile_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
