<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_officer_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('agent_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('officer_id')->constrained('users')->restrictOnDelete();

            // vet or adviser — the same officer could in principle hold both roles
            $table->string('role', 20);

            $table->timestamps();

            // one link per agent/officer/role, so admin cannot create the same route twice
            $table->unique(['agent_id', 'officer_id', 'role']);
            $table->index(['agent_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_officer_assignments');
    }
};
