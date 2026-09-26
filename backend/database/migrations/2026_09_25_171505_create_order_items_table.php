<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kiosk_product_id')->constrained()->restrictOnDelete();

            $table->unsignedInteger('quantity');

            // a snapshot, since kiosk_products.price can change after this order is placed
            $table->unsignedBigInteger('unit_price_at_order_time');

            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
