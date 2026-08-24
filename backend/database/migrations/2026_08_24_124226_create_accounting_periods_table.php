<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();

            $table->string('name')->unique();

            $table->date('starts_on');
            $table->date('ends_on');

            $table->string('status')->default('open');

            // who froze the period, and who later let it move again
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            // only the most recent reopening; every one of them is in the audit log
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // finding the period a transaction belongs to happens on every post
            $table->index(['starts_on', 'ends_on']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};
