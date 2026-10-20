<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // a farmer's, or an agent's for a farmer, request for a lender that is not in the list. The admin answers it once
        Schema::create('lender_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('farmer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('name_key');
            $table->string('type', 40);
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('lender_id')->nullable()->constrained()->nullOnDelete();
            $table->text('decision_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        // two requests for one name cannot wait at the same time, even when two people send them at once
        DB::statement("create unique index lender_requests_one_waiting_per_name on lender_requests (name_key) where status = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('lender_requests');
    }
};
