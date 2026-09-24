<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_product_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('kiosk_product_id')->constrained()->cascadeOnDelete();
            $table->string('path');

            $table->timestamps();

            $table->index('kiosk_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_product_images');
    }
};
