<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_unit_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('farm_unit_id')->constrained()->cascadeOnDelete();
            $table->string('path');

            $table->timestamps();

            $table->index('farm_unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_unit_images');
    }
};
