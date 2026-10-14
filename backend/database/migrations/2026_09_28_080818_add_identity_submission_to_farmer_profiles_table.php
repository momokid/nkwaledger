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

            // the farmer's photo, on the private disk
            $table->string('identity_photo_path')->nullable()->after('identity_submitted_at');

            // set when an admin sends the submission back; cleared by the next submission
            $table->string('identity_rejected_reason')->nullable()->after('identity_photo_path');
            $table->foreignId('identity_rejected_by')->nullable()->after('identity_rejected_reason')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('identity_rejected_at')->nullable()->after('identity_rejected_by');
        });
    }

    public function down(): void
    {
        Schema::table('farmer_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('identity_rejected_by');
            $table->dropConstrainedForeignId('identity_submitted_by');
            $table->dropColumn(['identity_submitted_at', 'identity_photo_path', 'identity_rejected_reason', 'identity_rejected_at']);
        });
    }
};
