<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // what the money for one farm is planned for: one of the farmer's own record types, or a note the farmer typed.
        // Older rows have neither
        Schema::table('loan_farm_allocations', function (Blueprint $table) {
            $table->foreignId('purpose_template_id')->nullable()->after('amount_minor')->constrained('transaction_templates')->restrictOnDelete();
            $table->string('purpose_note', 255)->nullable()->after('purpose_template_id');
        });
    }

    public function down(): void
    {
        Schema::table('loan_farm_allocations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purpose_template_id');
            $table->dropColumn('purpose_note');
        });
    }
};
