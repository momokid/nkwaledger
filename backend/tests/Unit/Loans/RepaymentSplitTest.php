<?php

use App\Services\Loans\RepaymentSplit;

// P = principal, I = agreed interest, then what has been paid of each so far
$split = fn(int $amount, int $p, int $i, int $paidP = 0, int $paidI = 0) => RepaymentSplit::compute($amount, $p, $i, $paidP, $paidI);

test('the amount is shared by interest over total, rounded half up', function () use ($split) {
    $a = $split(60000, 100000, 20000);
    $b = $split(3, 100000, 20000);
    $c = $split(1, 100000, 20000);

    expect([$a['interest'], $a['principal']])->toBe([10000, 50000])
        ->and([$b['interest'], $b['principal']])->toBe([1, 2])
        ->and([$c['interest'], $c['principal']])->toBe([0, 1]);
});

test('with no interest everything is principal', function () use ($split) {
    expect($split(4321, 100000, 0))->toMatchArray(['principal' => 4321, 'interest' => 0, 'excess' => 0]);
});

test('the parts always add up to the amount', function () use ($split) {
    foreach ([1, 2, 7, 333, 9999, 54321, 119999] as $amount) {
        $r = $split($amount, 100000, 20000);
        expect($r['principal'] + $r['interest'])->toBe($amount);
    }
});

test('paying off exactly what remains gives exactly the remaining parts, even after rounding drift', function () use ($split) {
    $paidP = 0;
    $paidI = 0;

    foreach ([33333, 33333, 33333] as $amount) {
        $r = $split($amount, 100000, 20000, $paidP, $paidI);
        $paidP += $r['principal'];
        $paidI += $r['interest'];
    }

    $last = $split(120000 - 99999, 100000, 20000, $paidP, $paidI);

    expect($last)->toMatchArray(['principal' => 100000 - $paidP, 'interest' => 20000 - $paidI, 'excess' => 0])
        ->and($paidP + $last['principal'])->toBe(100000)
        ->and($paidI + $last['interest'])->toBe(20000);
});

test('a part never goes past what remains of it: principal overflow goes to interest', function () use ($split) {
    // remaining principal 100, remaining interest 20000; the share would put 8333 on principal
    $r = $split(10000, 100000, 20000, 99900, 0);

    expect($r)->toMatchArray(['principal' => 100, 'interest' => 9900, 'excess' => 0]);
});

test('a part never goes past what remains of it: interest overflow goes to principal', function () use ($split) {
    // remaining interest 50; the share would put 1666 on interest
    $r = $split(10000, 100000, 20000, 0, 19950);

    expect($r)->toMatchArray(['principal' => 9950, 'interest' => 50, 'excess' => 0]);
});

test('more than what remains pays both off and the extra is interest', function () use ($split) {
    $r = $split(130000, 100000, 20000);

    expect($r)->toMatchArray(['principal' => 100000, 'interest' => 30000, 'excess' => 10000]);
});

test('interest paid beyond the agreed amount leaves nothing remaining, never a negative', function () use ($split) {
    $r = $split(500, 100000, 20000, 100000, 25000);

    expect($r)->toMatchArray(['remaining_principal' => 0, 'remaining_interest' => 0, 'principal' => 0, 'interest' => 500, 'excess' => 500]);
});

test('large amounts stay in integers', function () use ($split) {
    $r = $split(777_777_777, 1_000_000_000, 300_000_001);

    expect($r['principal'] + $r['interest'])->toBe(777_777_777)->and($r['interest'])->toBeInt();
});
