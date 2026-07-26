<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farmer_groups', function (Blueprint $table) {
            $table->foreignId('group_type_id')
                ->after('name')
                ->nullable()
                ->constrained('farmer_group_types')
                ->nullOnDelete();
        });

        Schema::table('farmer_groups', function (Blueprint $table) {
            $table->dropColumn('group_type');
        });
    }

    public function down(): void
    {
        Schema::table('farmer_groups', function (Blueprint $table) {
            $table->string('group_type')->after('name')->default('cooperative');
        });

        Schema::table('farmer_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_type_id');
        });
    }
};
