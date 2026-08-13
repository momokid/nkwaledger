<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // child dropped ahead of parent so any surviving foreign key clears first
        Schema::dropIfExists('ledger_account_types');
        Schema::dropIfExists('ledger_fundamental_types');
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Irreversible: ledger_account_types and ledger_fundamental_types were retired from the ledger design.'
        );
    }
};
