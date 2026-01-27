<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('one_time_passwords', function (Blueprint $table) {
            $table->id();

            // Phone number requesting the OTP (primary identity)
            $table->string('phone_number', 20)->index();

            // Hashed OTP (NEVER store raw OTP)
            $table->string('otp_hash');

            // Expiration timestamp (hard security boundary)
            $table->timestamp('expires_at');

            // Marks OTP as consumed (prevents reuse)
            $table->timestamp('used_at')->nullable();

            // Number of verification attempts (anti-bruteforce)
            $table->unsignedTinyInteger('attempts')->default(0);

            // Where the request came from (web, mobile, ussd)
            $table->string('channel', 20)->default('web');

            // Optional metadata for security audits
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            // Composite index for fast lookups & cleanup
            $table->index(['phone_number', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('one_time_passwords');
    }
};
