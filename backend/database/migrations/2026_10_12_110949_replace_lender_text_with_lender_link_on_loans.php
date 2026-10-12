<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // no loan data exists yet (development only), so the typed name and type are dropped, not carried over
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->foreignId('lender_id')->nullable()->after('farmer_profile_id')->constrained('lenders')->restrictOnDelete();
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['lender_name', 'lender_type']);
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->string('lender_name')->default('');
            $table->string('lender_type', 20)->default('other');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lender_id');
        });
    }
};
