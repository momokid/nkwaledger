<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disease_reports', function (Blueprint $table) {
            // a report saved offline has no photo until the phone sends it
            $table->string('photo_path')->nullable()->change();
            // the web form's retry key; a report made by sync is found through sync_submissions instead
            $table->uuid('client_uuid')->nullable()->unique()->after('uuid');
        });

        Schema::table('sync_submissions', function (Blueprint $table) {
            $table->string('type', 20)->default('transaction')->after('uuid');
            $table->foreignId('disease_report_id')->nullable()->after('transaction_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sync_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disease_report_id');
            $table->dropColumn('type');
        });

        Schema::table('disease_reports', function (Blueprint $table) {
            $table->dropUnique(['client_uuid']);
            $table->dropColumn('client_uuid');
            // photo_path stays nullable: rows without a photo may exist by now
        });
    }
};
