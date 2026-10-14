<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // the one shared list of institutions a loan can be borrowed from. Only an admin adds to it
        Schema::create('lenders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // bank, microfinance, credit_union or other
            $table->string('type', 20);
            $table->string('name');
            // lower case with single spaces, so two spellings of one name are one lender
            $table->string('name_key')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lenders');
    }
};
