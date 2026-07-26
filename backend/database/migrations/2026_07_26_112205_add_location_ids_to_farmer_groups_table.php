<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farmer_groups', function (Blueprint $table) {
            $table->foreignId('region_id')->after('group_type_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->foreignId('district_id')->after('region_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->foreignId('community_id')->after('district_id')->nullable()->constrained('communities')->nullOnDelete();
        });

        Schema::table('farmer_groups', function (Blueprint $table) {
            $table->dropColumn(['region', 'district', 'community']);
        });
    }

    public function down(): void
    {
        Schema::table('farmer_groups', function (Blueprint $table) {
            $table->string('region')->nullable()->after('group_type_id');
            $table->string('district')->nullable()->after('region');
            $table->string('community')->nullable()->after('district');
        });

        Schema::table('farmer_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('region_id');
            $table->dropConstrainedForeignId('district_id');
            $table->dropConstrainedForeignId('community_id');
        });
    }
};
