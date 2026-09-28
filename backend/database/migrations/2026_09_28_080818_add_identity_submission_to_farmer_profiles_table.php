<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farmer_profiles', function (Blueprint $table) {
            // who put the document on file, so the same person can never also approve it
            $table->foreignId('identity_submitted_by')->nullable()->after('identity_verified_by')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('identity_submitted_at')->nullable()->after('identity_submitted_by');
        });
    }

    public function down(): void
    {
        Schema::table('farmer_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('identity_submitted_by');
            $table->dropColumn('identity_submitted_at');
        });
    }
};
