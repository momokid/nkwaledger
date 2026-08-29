<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_units', function (Blueprint $table) {
            $table->id();

            $table->foreignId('farmer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_type_id')->constrained()->restrictOnDelete();

            // weather and crop calendars read this, never the farmer's home community
            $table->foreignId('community_id')->constrained()->restrictOnDelete();

            $table->string('name');
            $table->decimal('capacity', 12, 2)->nullable();
            $table->string('capacity_unit', 30)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // until this is set the unit accepts stock and transactions, but everything on it stays provisional
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('farmer_profile_id');
            $table->index('community_id');
            $table->index('approved_at');
        });

        // scoped to live rows, so a removed name can be used again
        DB::statement('CREATE UNIQUE INDEX farm_units_profile_name_unique ON farm_units (farmer_profile_id, name) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_units');
    }
};
