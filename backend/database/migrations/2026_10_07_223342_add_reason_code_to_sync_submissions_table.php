<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_submissions', function (Blueprint $table) {
            // why the server turned a record away by rule; code only, never the words
            $table->string('reason_code', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sync_submissions', function (Blueprint $table) {
            $table->dropColumn('reason_code');
        });
    }
};
