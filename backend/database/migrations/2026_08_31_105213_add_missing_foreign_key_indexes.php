<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // table, then the columns that get looked up by value
    protected array $indexes = [
        'transactions' => [
            'accounting_period_id',
            'transaction_template_id',
            'settlement_account_id',
            'recorded_by',
        ],
        'farm_units' => ['farm_type_id'],
        'farmer_profiles' => ['farmer_group_id'],
        'farmer_groups' => ['community_id', 'district_id', 'region_id'],
        'farm_types' => ['category_id'],
        'farmer_farm_types' => ['farm_type_id'],
        'role_has_permissions' => ['role_id'],
        'ledger_accounts' => ['subcategory_id'],
        'ledger_categories' => ['class_id'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->index($column);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->dropIndex([$column]);
                }
            });
        }
    }
};
