<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            // the entry table did not exist when this column was created
            $table->foreign('journal_entry_id')
                ->references('id')
                ->on('journal_entries')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropForeign(['journal_entry_id']);
        });
    }
};
