<?php

use App\Models\Lender;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The key a lender is searched and sorted by. Two names that fold to the same key are the same lender, so nothing is
// written until every existing row is known to fold to a key of its own; if two do not, it stops and says which.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('lenders', 'sort_key')) {
            return;
        }

        $rows = DB::table('lenders')->orderBy('id')->get(['id', 'name']);
        $keys = [];

        foreach ($rows as $row) {
            $keys[$row->id] = Lender::sortKeyOf($row->name);
        }

        $clashes = [];

        foreach ($rows->groupBy(fn($row) => $keys[$row->id]) as $key => $group) {
            if ($group->count() > 1) {
                $clashes[] = '"' . $group->pluck('name')->implode('" and "') . '"';
            }
        }

        if ($clashes !== []) {
            throw new RuntimeException('Two lenders have the same sort key, so nothing was changed. Rename one of each pair on the Lenders page, then run this again: ' . implode('; ', $clashes) . '.');
        }

        DB::transaction(function () use ($rows, $keys) {
            Schema::table('lenders', function (Blueprint $table) {
                $table->string('sort_key')->nullable()->after('name_key');
            });

            foreach ($rows as $row) {
                DB::table('lenders')->where('id', $row->id)->update(['sort_key' => $keys[$row->id]]);
            }

            Schema::table('lenders', function (Blueprint $table) {
                $table->string('sort_key')->nullable(false)->change();
                $table->unique('sort_key');
            });
        });

        $odd = $rows->filter(fn($row) => Lender::hasUnfoldable($row->name))->pluck('name');

        if ($rows->isNotEmpty()) {
            echo "Lender sort keys: {$rows->count()} filled. Names kept as they are; "
                . ($odd->isEmpty() ? 'none has a character that cannot be folded to plain letters.' : 'these have a character that cannot be folded, and the key keeps it: "' . $odd->implode('", "') . '".')
                . PHP_EOL;
        }
    }

    // the key is derived from the name, so there is nothing to put back
    public function down(): void {}
};
