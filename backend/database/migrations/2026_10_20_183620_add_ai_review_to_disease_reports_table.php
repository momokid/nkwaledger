<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disease_reports', function (Blueprint $table) {
            $table->string('ai_status', 20)->nullable();
            $table->foreignId('health_category_id')->nullable()->constrained('health_categories')->restrictOnDelete();
            $table->decimal('ai_confidence', 4, 3)->nullable();
            $table->boolean('ai_urgent')->default(false);
            $table->text('ai_note')->nullable();
            $table->string('ai_model')->nullable();
            $table->timestamp('ai_reviewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('disease_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('health_category_id');
            $table->dropColumn(['ai_status', 'ai_confidence', 'ai_urgent', 'ai_note', 'ai_model', 'ai_reviewed_at']);
        });
    }
};
