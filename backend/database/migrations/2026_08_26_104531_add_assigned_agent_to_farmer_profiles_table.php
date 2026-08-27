<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farmer_profiles', function (Blueprint $table) {
            // the agent who serves this farmer, separate from registered_by which only records who typed the row
            $table->foreignId('assigned_agent_id')->nullable()->after('registered_by')->constrained('users')->nullOnDelete();
            $table->index('assigned_agent_id');
        });
    }

    public function down(): void
    {
        Schema::table('farmer_profiles', function (Blueprint $table) {
            $table->dropForeign(['assigned_agent_id']);
            $table->dropIndex(['assigned_agent_id']);
            $table->dropColumn('assigned_agent_id');
        });
    }
};
