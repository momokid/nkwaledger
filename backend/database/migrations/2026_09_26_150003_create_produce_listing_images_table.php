<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // mirrors kiosk_product_images exactly - same shape, same conventions, a produce
    // listing's photos are no different a kind of thing than a kiosk product's
    public function up(): void
    {
        Schema::create('produce_listing_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('produce_listing_id')->constrained()->cascadeOnDelete();
            $table->string('path');

            $table->timestamps();

            $table->index('produce_listing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produce_listing_images');
    }
};
