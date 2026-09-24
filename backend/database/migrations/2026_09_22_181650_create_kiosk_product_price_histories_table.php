<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_product_price_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('kiosk_product_id')->constrained()->cascadeOnDelete();

            // null on the very first row, when the product had no earlier price
            $table->integer('old_price')->nullable();
            $table->integer('new_price');

            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);

            // a record of what happened has no later state to track
            $table->timestamp('created_at')->nullable();

            $table->index('kiosk_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_product_price_histories');
    }
};
