<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // set once by PostingService from the resolved settlement account, and never
            // changed after (nothing here ever is) - lets a cash/credit filter query
            // directly instead of joining out to journal lines every time
            $table->boolean('is_credit')->default(false)->after('settlement_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('is_credit');
        });
    }
};
