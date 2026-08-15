<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_categories', function (Blueprint $table) {
            $table->dropForeign(['fundamental_type_id']);
            $table->dropColumn(['fundamental_type_id', 'type', 'class']);

            $table->foreignId('class_id')
                ->after('name')
                ->constrained('ledger_classes')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ledger_categories', function (Blueprint $table) {
            $table->dropForeign(['class_id']);
            $table->dropColumn('class_id');

            $table->foreignId('fundamental_type_id')
                ->after('name')
                ->constrained('ledger_fundamental_types')
                ->restrictOnDelete();
            $table->string('type')->after('fundamental_type_id');
            $table->string('class')->after('type');
        });
    }
};
