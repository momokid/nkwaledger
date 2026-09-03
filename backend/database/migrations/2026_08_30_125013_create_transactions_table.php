<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // the row id never reaches the browser
            $table->uuid('uuid')->unique();

            // string, so a reference beginning with zero survives
            $table->string('reference', 12)->unique();

            $table->foreignId('farmer_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('transaction_template_id')->constrained()->restrictOnDelete();

            // copied from the template at posting, so editing a template cannot rewrite history
            $table->string('transaction_type', 20);

            $table->foreignId('accounting_period_id')->constrained()->restrictOnDelete();
            $table->date('transaction_date');

            // pesewas, always positive; the plus or minus lives in the journal lines
            $table->unsignedBigInteger('amount_minor');

            // cash, momo or bank; empty on a loss, where no money moves
            $table->foreignId('settlement_account_id')
                ->nullable()
                ->constrained('ledger_accounts')
                ->restrictOnDelete();

            $table->foreignId('farm_unit_id')->nullable()->constrained()->restrictOnDelete();

            $table->string('narration')->nullable();
            $table->string('channel', 20);

            // stamped once from the unit's approval at the moment of posting
            $table->boolean('is_provisional')->default(false);

            // one correction per original, enforced by the unique index
            $table->foreignId('reverses_transaction_id')
                ->nullable()
                ->unique()
                ->constrained('transactions')
                ->restrictOnDelete();

            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();

            // a phone that syncs the same record twice still posts it once
            $table->string('idempotency_key', 64)->nullable()->unique();

            $table->timestamp('posted_at');

            // no updated_at and no soft delete, because a row here never changes
            $table->timestamp('created_at')->nullable();

            // every statement reads one farmer over a range of dates
            $table->index(['farmer_profile_id', 'transaction_date']);
            $table->index(['farmer_profile_id', 'accounting_period_id']);
            $table->index(['farmer_profile_id', 'is_provisional']);
            $table->index('transaction_type');
            $table->index('farm_unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
