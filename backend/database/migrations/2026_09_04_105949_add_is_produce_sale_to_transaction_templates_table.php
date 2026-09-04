<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_templates', function (Blueprint $table) {
            // marks an income template as a produce/livestock sale, so PostingService
            // knows to ask for a quantity and reduce stock — other income (gifts, etc.) skips this
            $table->boolean('is_produce_sale')->default(false)->after('requires_farm_unit');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_templates', function (Blueprint $table) {
            $table->dropColumn('is_produce_sale');
        });
    }
};
