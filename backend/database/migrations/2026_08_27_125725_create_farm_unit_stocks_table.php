<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_unit_stocks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('farm_unit_id')->constrained()->cascadeOnDelete();

            $table->string('source', 20);

            // what was acquired, and what the cost belongs to
            $table->decimal('opening_quantity', 12, 2);
            // what is there today, moved only by stock movements
            $table->decimal('current_quantity', 12, 2);
            $table->string('unit_of_measure', 30)->nullable();

            $table->decimal('acquisition_cost', 14, 2)->default(0);

            $table->date('started_on');
            // a herd never closes, so this stays empty for most animals
            $table->date('ended_on')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('farm_unit_id');
            $table->index('confirmed_at');
            $table->index('ended_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_unit_stocks');
    }
};
