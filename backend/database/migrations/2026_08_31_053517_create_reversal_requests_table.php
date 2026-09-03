<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reversal_requests', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            // one ask per transaction, so nobody queues up two cancellations
            $table->foreignId('transaction_id')->unique()->constrained()->restrictOnDelete();

            $table->text('reason');

            $table->string('status', 20)->default('pending');

            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');

            // whoever asked cannot be whoever agrees
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // the correction this ask produced, once it was agreed
            $table->foreignId('reversal_transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reversal_requests');
    }
};
