<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ledger_periods', function (Blueprint $table) {
            $table->id();
            $table->date("start_date");
            $table->date("end_date");
            $table->boolean("is_locked")->default(false);

            //Save who locked it for audit 
            $table->foreignId("locked_by")->nullable()->references("id")->on("users");
            $table->timestamp("locked_at")->nullable();
            $table->timestamps();

            //prevent overlapping periods
            $table->unique(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_periods');
    }
};
