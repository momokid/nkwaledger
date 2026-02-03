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
        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('transaction_date');
            $table->enum('type', ['income', 'expense', 'loss',]);
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->foreignId('approved_by')->nullable()->references('id')->on('users')->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->string('approval_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_transactions');
    }
};
