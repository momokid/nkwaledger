<?php

// The concurrency tests start real child processes. A child that cannot boot must fail loudly, never look like a race.

use PHPUnit\Framework\AssertionFailedError;

test('a child process can boot and build what the loan controllers need', function () {
    $process = proc_open([PHP_BINARY, base_path('tests/Support/concurrent-accept.php'), 'boot'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), array_merge(getenv(), ['APP_ENV' => 'testing']));
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    proc_close($process);

    $answer = childAnswer($out, $err);

    expect($answer['status'])->toBe(200)->and($answer['body'])->toBe('ok');
});

test('a child that printed nothing is reported as one that could not start', function () {
    expect(fn() => childAnswer('', 'boom'))->toThrow(AssertionFailedError::class, 'the child could not start: boom');
});

test('a child that answered 500 is reported as one that could not start', function () {
    expect(fn() => childAnswer(json_encode(['status' => 500, 'body' => '<html><b>Server Error</b></html>']), ''))
        ->toThrow(AssertionFailedError::class, 'the child could not start: Server Error');
});

test('a child that answered normally is passed through', function () {
    expect(childAnswer(json_encode(['status' => 302, 'body' => 'x']), ''))->toBe(['status' => 302, 'body' => 'x']);
});
