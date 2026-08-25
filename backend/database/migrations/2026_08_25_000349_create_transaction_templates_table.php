<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('transaction_type');
            $table->foreignId('debit_account_id')
                ->constrained('ledger_accounts')
                ->restrictOnDelete();
            $table->foreignId('credit_account_id')
                ->constrained('ledger_accounts')
                ->restrictOnDelete();
            $table->string('settlement_side')->default('none');
            $table->boolean('requires_farm_unit')->default(false);
            $table->foreignId('farm_type_category_id')
                ->nullable()
                ->constrained('farm_type_categories')
                ->nullOnDelete();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['transaction_type', 'is_active']);
            $table->index('farm_type_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_templates');
    }
};
