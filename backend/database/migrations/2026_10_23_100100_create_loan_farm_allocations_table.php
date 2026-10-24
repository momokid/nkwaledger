<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // the part of a new money loan the farmer plans to use on each farm unit; the parts add up to the loan
        Schema::create('loan_farm_allocations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farm_unit_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();

            $table->unique(['loan_id', 'farm_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_farm_allocations');
    }
};
