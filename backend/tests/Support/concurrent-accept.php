<?php

// child process for ConcurrentAcceptPgTest: boots the app, waits for the shared start time, then sends one real
// request that accepts a pending loan entry - the farmer's Accept button, or the agent typing the code
$boot = ($argv[1] ?? '') === 'boot';

if (! $boot) {
    [, $mode, $userId, $entryUuid, $code, $startAt] = $argv;
}

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$http = $app->make(Illuminate\Contracts\Http\Kernel::class);
$http->bootstrap();

if ($boot) {
    // builds what both requests need, so a missing setting fails here
    $app->make(App\Http\Controllers\Loans\PendingEntryController::class);
    $app->make(App\Http\Controllers\Loans\AgentLoanController::class);
    echo json_encode(['status' => 200, 'body' => 'ok']);
    exit;
}

$request = $mode === 'app'
    ? Illuminate\Http\Request::create("/loans/entries/{$entryUuid}/accept", 'POST')
    : Illuminate\Http\Request::create("/agent/loan-entries/{$entryUuid}/code", 'POST', ['code' => $code]);

$app->instance('request', $request);
Illuminate\Support\Facades\Auth::guard('web')->setUser(App\Models\User::findOrFail((int) $userId));

while (microtime(true) < (float) $startAt) {
}

$response = $http->handle($request);

echo json_encode(['status' => $response->getStatusCode(), 'body' => substr((string) $response->getContent(), 0, 300)]);
