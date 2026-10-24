<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // a purchase the farmer says was paid from one of their loans
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('loan_id')->nullable()->after('farm_unit_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loan_id');
        });
    }
};
