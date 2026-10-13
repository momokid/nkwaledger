<?php

// child process for ConcurrentLenderAddPgTest: boots the app, waits for the shared start time, then sends one real
// request: the admin adding an institution
[, $userId, $name, $startAt] = $argv;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$http = $app->make(Illuminate\Contracts\Http\Kernel::class);
$http->bootstrap();

$request = Illuminate\Http\Request::create('/admin/lenders', 'POST', ['type' => 'bank', 'name' => $name], [], [], ['HTTP_ACCEPT' => 'application/json']);

$app->instance('request', $request);
Illuminate\Support\Facades\Auth::guard('web')->setUser(App\Models\User::findOrFail((int) $userId));

while (microtime(true) < (float) $startAt) {
}

$response = $http->handle($request);

echo json_encode(['status' => $response->getStatusCode(), 'body' => substr((string) $response->getContent(), 0, 300)]);
