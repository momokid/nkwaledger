<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_report_uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('disease_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('kind', 10);
            $table->unsignedBigInteger('total_bytes');
            $table->string('sha256', 64);
            // the bytes so far; their size is how much the server has
            $table->string('part_path');
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->unique(['disease_report_id', 'kind']);
        });

        Schema::table('disease_reports', function (Blueprint $table) {
            // where photo_path and audio_path live: 'public' for older reports, the private photo disk for uploaded ones
            $table->string('media_disk', 20)->default('public');
        });
    }

    public function down(): void
    {
        Schema::table('disease_reports', function (Blueprint $table) {
            $table->dropColumn('media_disk');
        });

        Schema::dropIfExists('health_report_uploads');
    }
};
