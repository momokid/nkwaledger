<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // what an agent recorded for a farmer, waiting for the farmer. Generic on purpose: a repayment
        // later is another entry_type with its own payload. It never touches the books until accepted
        Schema::create('pending_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('farmer_profile_id')->constrained()->cascadeOnDelete();
            // loan.money or loan.existing today
            $table->string('entry_type', 40);
            // validated values only: integer pesewas, ids, dates and text the agent typed
            $table->json('payload');
            // pending, accepted, declined or withdrawn - the only place this state is kept
            $table->string('status', 20)->default('pending');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            // app or code
            $table->string('decided_via', 10)->nullable();
            $table->timestamp('decided_at')->nullable();
            // what acceptance made, so the entry points at it
            $table->string('result_type', 40)->nullable();
            $table->unsignedBigInteger('result_id')->nullable();
            $table->timestamps();

            $table->index(['farmer_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_entries');
    }
};
