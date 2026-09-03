<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();

            // one transaction gets one entry, never two
            $table->foreignId('transaction_id')->unique()->constrained()->restrictOnDelete();

            $table->string('narration')->nullable();

            $table->timestamp('posted_at');

            // no updated_at and no soft delete, because a row here never changes
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
