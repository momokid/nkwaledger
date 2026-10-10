<?php

// child process for ConcurrentPostPgTest: boots the app, waits for the shared start time,
// then sends one real request through the web path or the sync path
[, $mode, $userId, $farmerUuid, $uuid, $templateId, $settlementId, $startAt] = $argv;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$http = $app->make(Illuminate\Contracts\Http\Kernel::class);
$http->bootstrap();

$today = date('Y-m-d');
$web = $mode === 'web';
$payload = $web
    ? ['transaction_template_id' => (int) $templateId, 'amount' => '100', 'settlement_account_id' => (int) $settlementId, 'transaction_date' => $today, 'idempotency_key' => $uuid]
    : ['records' => [['uuid' => $uuid, 'template' => (int) $templateId, 'farmer' => $farmerUuid, 'amount' => '100', 'settlement_account_id' => (int) $settlementId, 'event_date' => $today, 'device_created_at' => date('c')]]];

$request = Illuminate\Http\Request::create(
    $web ? '/my-records' : '/sync/submissions',
    'POST',
    [],
    [],
    [],
    ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
    json_encode($payload),
);

$app->instance('request', $request);
Illuminate\Support\Facades\Auth::guard('web')->setUser(App\Models\User::findOrFail((int) $userId));

while (microtime(true) < (float) $startAt) {
}

$response = $http->handle($request);

echo json_encode(['status' => $response->getStatusCode(), 'body' => $response->getContent()]);
