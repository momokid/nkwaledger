<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            // how an agent's entry was accepted: app or code. Empty for a loan the farmer entered
            $table->string('accepted_via', 10)->nullable()->after('recorded_by');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('accepted_via');
        });
    }
};
