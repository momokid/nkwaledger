<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();

            // no foreign key yet, journal_entries is built next
            $table->unsignedBigInteger('journal_entry_id');

            $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete();

            // pesewas, and exactly one of the two carries the money
            $table->unsignedBigInteger('debit_minor')->default(0);
            $table->unsignedBigInteger('credit_minor')->default(0);

            // the order the lines print in on a statement
            $table->unsignedSmallInteger('line_number');

            // no updated_at and no soft delete, because a row here never changes
            $table->timestamp('created_at')->nullable();

            $table->unique(['journal_entry_id', 'line_number']);

            // every account balance reads this pair
            $table->index(['ledger_account_id', 'journal_entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
