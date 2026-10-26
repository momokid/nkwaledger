<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Show', [
            'profile' => [
                'name' => implode(' ', array_filter([$user->first_name, $user->other_name, $user->surname])),
                'phone' => $user->phone,
                'email' => $user->email,
            ],
        ]);
    }
}
