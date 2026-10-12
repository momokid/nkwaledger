<?php

// child process for ConcurrentAcceptPgTest: boots the app, waits for the shared start time, then sends one real
// request that accepts a pending loan entry - the farmer's Accept button, or the agent typing the code
[, $mode, $userId, $entryUuid, $code, $startAt] = $argv;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$http = $app->make(Illuminate\Contracts\Http\Kernel::class);
$http->bootstrap();

$request = $mode === 'app'
    ? Illuminate\Http\Request::create("/loans/entries/{$entryUuid}/accept", 'POST')
    : Illuminate\Http\Request::create("/agent/loan-entries/{$entryUuid}/code", 'POST', ['code' => $code]);

$app->instance('request', $request);
Illuminate\Support\Facades\Auth::guard('web')->setUser(App\Models\User::findOrFail((int) $userId));

while (microtime(true) < (float) $startAt) {
}

$response = $http->handle($request);

echo json_encode(['status' => $response->getStatusCode(), 'body' => substr((string) $response->getContent(), 0, 300)]);
