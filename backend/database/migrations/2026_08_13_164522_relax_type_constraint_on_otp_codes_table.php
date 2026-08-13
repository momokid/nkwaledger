<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // postgres keeps the rule as a named check, sqlite bakes it into the column
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE otp_codes DROP CONSTRAINT IF EXISTS otp_codes_type_check');

            return;
        }

        Schema::table('otp_codes', function (Blueprint $table) {
            $table->string('type')->change();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Irreversible: the otp type list is now held in application code.');
    }
};
