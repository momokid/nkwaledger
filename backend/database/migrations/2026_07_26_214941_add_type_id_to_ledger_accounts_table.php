<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->foreignId('type_id')
                ->after('name')
                ->nullable()
                ->constrained('ledger_account_types')
                ->restrictOnDelete();
        });

        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->dropColumn(['type', 'normal_balance']);
        });
    }

    public function down(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->string('type')->after('name')->default('asset');
            $table->string('normal_balance')->after('type')->default('debit');
        });

        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('type_id');
        });
    }
};
