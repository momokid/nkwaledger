<?php

// child process for ConcurrentRepaymentPgTest: boots the app, waits for the shared start time, then sends one real
// request that records a repayment on a loan as the farmer
[, $userId, $loanUuid, $amount, $key, $startAt] = $argv;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$http = $app->make(Illuminate\Contracts\Http\Kernel::class);
$http->bootstrap();

$user = App\Models\User::findOrFail((int) $userId);
$request = Illuminate\Http\Request::create("/loans/{$loanUuid}/repayments", 'POST', [
    'amount' => $amount, 'date' => now()->toDateString(), 'paid_from' => 'cash', 'idempotency_key' => $key,
]);

$app->instance('request', $request);
Illuminate\Support\Facades\Auth::guard('web')->setUser($user);

while (microtime(true) < (float) $startAt) {
}

$response = $http->handle($request);

echo json_encode(['status' => $response->getStatusCode(), 'error' => $request->hasSession() ? $request->session()->get('error') : null]);
