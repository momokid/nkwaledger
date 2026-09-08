<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weather_advisory_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('condition');
            $table->string('headline');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['community_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_advisory_logs');
    }
};
