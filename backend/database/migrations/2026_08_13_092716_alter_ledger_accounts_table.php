<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('type_id');
        });

        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->foreignId('control_id')
                ->after('name')
                ->constrained('ledger_controls')
                ->restrictOnDelete();
            $table->foreignId('subcategory_id')
                ->after('control_id')
                ->constrained('ledger_subcategories')
                ->restrictOnDelete();
            $table->foreignId('type_id')
                ->after('subcategory_id')
                ->constrained('ledger_types')
                ->restrictOnDelete();
            $table->string('account_code')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('control_id');
            $table->dropConstrainedForeignId('subcategory_id');
            $table->dropConstrainedForeignId('type_id');
            $table->dropColumn('account_code');
        });

        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->foreignId('type_id')
                ->after('name')
                ->nullable()
                ->constrained('ledger_account_types')
                ->restrictOnDelete();
        });
    }
};
