<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // the longest type key, credit_financial_institution, does not fit in 20 characters
    public function up(): void
    {
        Schema::table('lenders', function (Blueprint $table) {
            $table->string('type', 40)->change();
        });
    }

    // left wide on purpose: narrowing it could cut a type key
    public function down(): void {}
};
