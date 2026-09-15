<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disease_reports', function (Blueprint $table) {
            // an optional voice note alongside the photo; null on every report until a farmer records one
            $table->string('audio_path')->nullable()->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('disease_reports', function (Blueprint $table) {
            $table->dropColumn('audio_path');
        });
    }
};
