<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disease_reports', function (Blueprint $table) {
            $table->foreignId('officer_health_category_id')->nullable()->constrained('health_categories')->restrictOnDelete();
            $table->string('officer_verdict', 20)->nullable();
            $table->timestamp('officer_reviewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('disease_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('officer_health_category_id');
            $table->dropColumn(['officer_verdict', 'officer_reviewed_at']);
        });
    }
};
