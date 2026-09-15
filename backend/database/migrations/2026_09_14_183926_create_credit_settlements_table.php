<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_settlements', function (Blueprint $table) {
            $table->id();

            // the original sale/purchase this pays down
            $table->foreignId('transaction_id')->constrained('transactions')->restrictOnDelete();

            // the "Payment received"/"Payment made" adjustment that pays it, one row each -
            // a settlement transaction can never pay down more than the one record it posted for
            $table->foreignId('settlement_transaction_id')->unique()->constrained('transactions')->restrictOnDelete();

            $table->unsignedBigInteger('amount_minor');

            // a settlement is a fact that happened, never edited afterward
            $table->timestamp('created_at')->nullable();

            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_settlements');
    }
};
