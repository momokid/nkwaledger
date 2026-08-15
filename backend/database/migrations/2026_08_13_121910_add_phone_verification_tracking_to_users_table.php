<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('logins_since_verification')->default(0);
            $table->unsignedTinyInteger('verification_login_threshold')->nullable();
            $table->timestamp('next_verification_at')->nullable();

            $table->index('next_verification_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['next_verification_at']);
            $table->dropColumn([
                'logins_since_verification',
                'verification_login_threshold',
                'next_verification_at',
            ]);
        });
    }
};
