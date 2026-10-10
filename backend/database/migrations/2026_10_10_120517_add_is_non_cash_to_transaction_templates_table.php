<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_templates', function (Blueprint $table) {
            // no money moves but value does (goods, opening entries): records of this template carry
            // their own amount and are never in the cash totals, the balance or the class totals
            $table->boolean('is_non_cash')->default(false)->after('is_liability');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_templates', function (Blueprint $table) {
            $table->dropColumn('is_non_cash');
        });
    }
};
