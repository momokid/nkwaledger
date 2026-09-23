<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_products', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->foreignId('kiosk_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_product_id')->constrained()->cascadeOnDelete();

            // pesewas, like every other amount in the app
            $table->integer('price');

            $table->boolean('in_stock')->default(true);
            $table->date('expiry_date')->nullable();

            // parsed from a GS1 Digital Link QR when present; no column elsewhere for it
            $table->string('batch_number')->nullable();

            $table->timestamp('price_confirmed_at')->nullable();

            // null means no alert is currently outstanding; set when one goes out,
            // cleared the moment the thing it was about is no longer true
            $table->timestamp('stale_alerted_at')->nullable();
            $table->timestamp('expiry_alerted_at')->nullable();

            $table->string('status', 20)->default('active');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['kiosk_id', 'catalog_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_products');
    }
};
