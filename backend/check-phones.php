<?php

use App\Models\User;
use App\Support\Phone;

User::select('id', 'phone')->orderBy('id')->get()->each(function ($user) {
    $clean = Phone::normalise($user->phone);
    $flag  = $clean === $user->phone ? 'ok' : ($clean === null ? 'UNUSABLE' : 'NEEDS FIX -> ' . $clean);

    echo str_pad((string) $user->id, 5) . str_pad((string) $user->phone, 20) . $flag . PHP_EOL;
});
