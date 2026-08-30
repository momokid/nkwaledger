<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            // ticks the few accounts a farmer's money can actually sit in
            $table->boolean('is_settlement')->default(false)->after('is_system');

            $table->index('is_settlement');
        });
    }

    public function down(): void
    {
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->dropIndex(['is_settlement']);
            $table->dropColumn('is_settlement');
        });
    }
};
