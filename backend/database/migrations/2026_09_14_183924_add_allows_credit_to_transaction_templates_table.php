<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_templates', function (Blueprint $table) {
            // a sale or purchase may be settled against Receivable/Payable instead of
            // Cash/MoMo - every other template (services, losses, corrections) may not
            $table->boolean('allows_credit')->default(false)->after('is_stock_purchase');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_templates', function (Blueprint $table) {
            $table->dropColumn('allows_credit');
        });
    }
};
