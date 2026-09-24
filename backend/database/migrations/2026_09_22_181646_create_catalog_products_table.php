<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_products', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            // null means no barcode was given; present and unique means the first
            // supplier to scan/type it created this shared record
            $table->string('barcode')->nullable()->unique();
            $table->string('barcode_type', 20)->nullable();

            $table->string('name');
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('product_units')->nullOnDelete();
            $table->decimal('pack_quantity', 10, 2)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('seeded')->default(false);

            // a retired duplicate points at the record it was folded into; never removed
            $table->foreignId('merged_into_id')->nullable()->constrained('catalog_products')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_products');
    }
};
