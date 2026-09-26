<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // null for a system action (the auto-close command)
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('event_type', 20);
            $table->timestamp('occurred_at');

            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
    }
};
