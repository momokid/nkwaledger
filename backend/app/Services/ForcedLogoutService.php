<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// ends every way a user is signed in, on every device at once
class ForcedLogoutService
{
    public function signOutEverywhere(User $user): void
    {
        DB::transaction(function () use ($user) {
            // every browser session of theirs: the cookie each phone holds points at nothing from now on
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();

            // a remember-me cookie carries the old token, and is worth nothing once the token changes
            $user->forceFill(['remember_token' => Str::random(60)])->save();
        });
    }
}
