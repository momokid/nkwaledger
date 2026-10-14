<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_category_kiosk_product', function (Blueprint $table) {
            $table->id();

            $table->foreignId('marketplace_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kiosk_product_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['marketplace_category_id', 'kiosk_product_id'], 'mkt_cat_kiosk_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_category_kiosk_product');
    }
};
