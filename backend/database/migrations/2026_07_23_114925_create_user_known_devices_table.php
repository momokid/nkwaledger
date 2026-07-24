<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_known_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('fingerprint');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_known_devices');
    }
};
