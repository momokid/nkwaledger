<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('group_type');
            $table->string('region')->nullable();
            $table->string('district')->nullable();
            $table->string('community')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_shared_liability')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmer_groups');
    }
};