<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fundamental_type_id')
                ->constrained('ledger_fundamental_types')
                ->restrictOnDelete();
            $table->string('name')->unique();
            $table->string('type');
            $table->string('class');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_categories');
    }
};
