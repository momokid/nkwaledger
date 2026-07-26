<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_types', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->after('name')
                ->nullable()
                ->constrained('farm_type_categories')
                ->nullOnDelete();
        });

        Schema::table('farm_types', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('farm_types', function (Blueprint $table) {
            $table->string('category')->after('name')->default('crop');
        });

        Schema::table('farm_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
