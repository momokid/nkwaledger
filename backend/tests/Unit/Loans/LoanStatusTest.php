<?php

use App\Models\Loan;
use Illuminate\Support\Carbon;

$today = Carbon::parse('2026-10-18');
$status = fn(string $stored, int $balance, ?string $due) => Loan::statusFor($stored, $balance, $due === null ? null : Carbon::parse($due), $today);

test('a loan with a balance is active until the day after its due date', function () use ($status) {
    expect($status('active', 100, '2026-10-18'))->toBe('active')
        ->and($status('active', 100, '2026-10-19'))->toBe('active')
        ->and($status('active', 100, '2026-10-17'))->toBe('overdue');
});

test('a loan is in default 90 days after its due date', function () use ($status) {
    expect($status('active', 100, '2026-07-21'))->toBe('overdue')
        ->and($status('active', 100, '2026-07-20'))->toBe('default');
});

test('a loan with no due date is never overdue', function () use ($status) {
    expect($status('active', 100, null))->toBe('active');
});

test('paying it off or cancelling it clears overdue and default', function () use ($status) {
    expect($status('active', 0, '2026-01-01'))->toBe('repaid')
        ->and($status('cancelled', 100, '2026-01-01'))->toBe('cancelled');
});
