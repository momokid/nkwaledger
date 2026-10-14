<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_submissions', function (Blueprint $table) {
            $table->dropUnique(['client_uuid']);
            $table->unique(['user_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        Schema::table('sync_submissions', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'client_uuid']);
            $table->unique('client_uuid');
        });
    }
};
