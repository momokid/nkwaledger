<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_category_produce_listing', function (Blueprint $table) {
            $table->id();

            $table->foreignId('marketplace_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produce_listing_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['marketplace_category_id', 'produce_listing_id'], 'mkt_cat_produce_listing_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_category_produce_listing');
    }
};
