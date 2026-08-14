<?php

use Illuminate\Support\Facades\Route;

// routes an unverified user is allowed to reach, everything else must carry the gate
$exempt = [
    'dashboard',
    'farmer.dashboard',
    'agent.dashboard',
    'vet.dashboard',
    'adviser.dashboard',
    'supplier.dashboard',
    'admin.dashboard',
    'logout',
    'otp.create',
    'otp.store',
    'otp.resend',
    'otp.phone.send',
    'otp.phone.confirm',
    'password.confirm',
    'password.update',
    'verification.notice',
    'verification.verify',
    'verification.send',
    'auth/check',
    'confirm-password',
];

test('every authenticated route carries the phone gate', function () use ($exempt) {
    $missing = [];

    foreach (Route::getRoutes() as $route) {
        $middleware = $route->gatherMiddleware();

        // only routes behind login need the gate
        if (! in_array('auth', $middleware, true)) {
            continue;
        }

        if (in_array('verified.phone', $middleware, true)) {
            continue;
        }

        $name = $route->getName() ?? $route->uri();

        if (in_array($name, $exempt, true)) {
            continue;
        }

        $missing[] = $name;
    }

    expect($missing)->toBe([]);
});
