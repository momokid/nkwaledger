<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_submissions', function (Blueprint $table) {
            // the farmer cleared a rejected record from their own list; the row itself stays
            $table->timestamp('dismissed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sync_submissions', function (Blueprint $table) {
            $table->dropColumn('dismissed_at');
        });
    }
};
