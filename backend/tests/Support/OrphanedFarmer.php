<?php

use App\Models\FarmerProfile;
use Illuminate\Support\Facades\DB;

// a farmer profile whose account is gone, which the schema normally forbids. The foreign key is
// switched off for the rest of the test's own transaction only: deferred on SQLite, and dropped on
// PostgreSQL (DDL there is part of the transaction too, so it comes back when the test rolls back).
function orphanFarmerProfile(FarmerProfile $profile): FarmerProfile
{
    if (DB::getDriverName() === 'pgsql') {
        DB::statement('ALTER TABLE farmer_profiles DROP CONSTRAINT farmer_profiles_user_id_foreign');
    } else {
        DB::statement('PRAGMA defer_foreign_keys = ON');
    }

    DB::table('farmer_profiles')->where('id', $profile->id)->update(['user_id' => 987654]);

    return $profile->fresh();
}
