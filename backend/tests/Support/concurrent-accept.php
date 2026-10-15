<?php

// child process for the concurrency tests on pending loan entries: boots the app, waits for the shared start time, then
// sends one real request - the farmer's Accept button, the agent typing the code or sending a new one, or the admin
// approving the cancellation of a loan's record ($entryUuid is then the cancellation request's uuid).
// It also reports its process id, when it woke and finished, and when it first asked for and got a lock on a loan,
// a pending entry and a code, so a test can prove that two requests really ran together
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

$request = match ($mode) {
    'app' => Illuminate\Http\Request::create("/loans/entries/{$entryUuid}/accept", 'POST'),
    'send' => Illuminate\Http\Request::create("/agent/loan-entries/{$entryUuid}/send-code", 'POST'),
    'cancel' => Illuminate\Http\Request::create("/admin/reversals/{$entryUuid}/approve", 'PATCH'),
    default => Illuminate\Http\Request::create("/agent/loan-entries/{$entryUuid}/code", 'POST', ['code' => $code]),
};

$events = [];

Illuminate\Support\Facades\DB::listen(function (Illuminate\Database\Events\QueryExecuted $query) use (&$events) {
    $sql = strtolower($query->sql);
    $locks = str_contains($sql, 'for update');
    $kind = match (true) {
        $locks && str_contains($sql, 'from "loans"'), str_starts_with($sql, 'update "loans"') => 'loan',
        $locks && str_contains($sql, 'from "pending_entries"') => 'entry',
        str_contains($sql, '"pending_entry_codes"') && ($locks || str_starts_with($sql, 'update') || str_starts_with($sql, 'insert')) => 'code',
        default => null,
    };

    if ($kind !== null && ! isset($events[$kind])) {
        $got = microtime(true);
        $events[$kind] = ['asked' => round($got - $query->time / 1000, 4), 'got' => round($got, 4), 'waited_ms' => round($query->time, 1)];
    }
});

$app->instance('request', $request);
Illuminate\Support\Facades\Auth::guard('web')->setUser(App\Models\User::findOrFail((int) $userId));

while (microtime(true) < (float) $startAt) {
}

$wake = microtime(true);
$response = $http->handle($request);

echo json_encode([
    'status' => $response->getStatusCode(),
    'body' => substr((string) $response->getContent(), 0, 300),
    'pid' => getmypid(),
    'wake' => round($wake, 4),
    'done' => round(microtime(true), 4),
    'events' => $events,
]);
