<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // one row each time an admin checks a loan with its lender. Written once, never edited or deleted;
        // the newest row for a loan is its current status
        Schema::create('loan_checks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20);
            $table->text('note')->nullable();
            $table->string('idempotency_key', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('loan_id');
            $table->unique(['loan_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_checks');
    }
};