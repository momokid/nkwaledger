<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_unit_stocks', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('confirmed_by');
            $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 255)->nullable()->after('rejected_by');
        });

        Schema::table('farm_unit_stock_movements', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('confirmed_by');
            $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 255)->nullable()->after('rejected_by');
        });
    }

    public function down(): void
    {
        Schema::table('farm_unit_stocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['rejected_at', 'rejection_reason']);
        });

        Schema::table('farm_unit_stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['rejected_at', 'rejection_reason']);
        });
    }
};
