<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_reminders', function (Blueprint $table) {
            $table->id();

            // the still-outstanding credit sale/purchase this reminder is about - a
            // transaction is immutable, so "when was the last reminder sent" lives here
            // instead of as a column on the transaction itself
            $table->foreignId('transaction_id')->constrained('transactions')->restrictOnDelete();

            // a reminder is a fact that happened, never edited afterward
            $table->timestamp('created_at')->nullable();

            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_reminders');
    }
};
