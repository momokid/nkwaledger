<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // one row per code sent. Only a keyed hash is kept: the code itself is never stored anywhere
        Schema::create('pending_entry_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pending_entry_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash', 64);
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('used_at')->nullable();
            // set when a newer code replaces this one
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['pending_entry_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_entry_codes');
    }
};
