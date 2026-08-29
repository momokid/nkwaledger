<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_unit_stock_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('farm_unit_stock_id')->constrained()->cascadeOnDelete();

            $table->string('reason', 20);
            // a movement of nothing is a mistake, not a record
            $table->decimal('quantity', 12, 2)->check('quantity > 0');
            // most reasons decide this themselves, a correction is told which way
            $table->boolean('is_increase');

            $table->date('occurred_on');
            $table->string('note')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('farm_unit_stock_id');
            $table->index('confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_unit_stock_movements');
    }
};
