<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disease_reports', function (Blueprint $table) {
            $table->id();

            // the row id never reaches the browser
            $table->uuid('uuid')->unique();

            $table->foreignId('farm_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('farmer_profile_id')->constrained()->restrictOnDelete();

            // the agent who typed this in for the farmer; null when the farmer submitted it themself
            $table->foreignId('reported_by')->nullable()->constrained('users')->restrictOnDelete();

            // copied from farm_type.category at submission, so a later category change
            // (or a change of farm unit) can never rewrite what this report was actually about
            $table->string('category', 20);
            $table->string('routed_role', 20);

            // null means no vet/adviser is linked to this farmer's agent yet — it waits in the admin queue
            $table->foreignId('assigned_officer_id')->nullable()->constrained('users')->restrictOnDelete();

            $table->string('status', 20)->default('new');

            $table->string('photo_path');
            $table->text('description');

            $table->string('contact_method', 20)->nullable();
            $table->text('response_note')->nullable();

            $table->timestamps();

            // the officer's queue, and the admin queue for whichever role is unassigned
            $table->index(['assigned_officer_id', 'status']);
            $table->index(['routed_role', 'assigned_officer_id']);
            // a farm unit's past report history, and a farmer's own report list
            $table->index(['farm_unit_id', 'created_at']);
            $table->index(['farmer_profile_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disease_reports');
    }
};
