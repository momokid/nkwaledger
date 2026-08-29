<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmer_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();

            $table->foreignId('community_id')->constrained()->restrictOnDelete();
            $table->foreignId('farmer_group_id')->nullable()->constrained('farmer_groups')->nullOnDelete();

            $table->string('identity_type', 20)->nullable();
            $table->string('identity_number_hash', 64)->nullable();
            $table->timestamp('identity_verified_at')->nullable();
            $table->foreignId('identity_verified_by')->nullable()->constrained('users')->nullOnDelete();

            // null means the farmer registered themselves, otherwise the agent who did it
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('onboarded_at')->nullable();
            $table->timestamp('opening_balance_posted_at')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // one document per person, scoped by type so a passport and a voter card may share digits
            $table->unique(['identity_type', 'identity_number_hash']);

            $table->index('community_id');
            $table->index('registered_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmer_profiles');
    }
};
