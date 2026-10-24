<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ussd_pin_hash')->nullable();
            $table->unsignedTinyInteger('ussd_pin_attempts')->default(0);
            $table->timestamp('ussd_pin_locked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ussd_pin_hash', 'ussd_pin_attempts', 'ussd_pin_locked_at']);
        });
    }
};
