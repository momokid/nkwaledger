<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_templates', function (Blueprint $table) {
            // marks a purchase template as buying the farm unit's own tracked stock (animals,
            // fingerlings), so PostingService knows to ask for a quantity and increase the count —
            // buying an input like feed or seed skips this
            $table->boolean('is_stock_purchase')->default(false)->after('is_produce_sale');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_templates', function (Blueprint $table) {
            $table->dropColumn('is_stock_purchase');
        });
    }
};
