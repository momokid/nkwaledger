<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_subcategories', function (Blueprint $table) {
            $table->foreignId('type_id')
                ->after('name')
                ->constrained('ledger_types')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ledger_subcategories', function (Blueprint $table) {
            $table->dropForeign(['type_id']);
            $table->dropColumn('type_id');
        });
    }
};
