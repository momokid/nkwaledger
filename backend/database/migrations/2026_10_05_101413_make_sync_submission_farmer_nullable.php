<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // a record naming a farmer that does not exist is kept without a farmer reference
    public function up(): void
    {
        Schema::table('sync_submissions', function (Blueprint $table) {
            $table->foreignId('farmer_profile_id')->nullable()->change();
        });
    }

    // fails if any row has no farmer, since the column goes back to NOT NULL
    public function down(): void
    {
        Schema::table('sync_submissions', function (Blueprint $table) {
            $table->foreignId('farmer_profile_id')->nullable(false)->change();
        });
    }
};
