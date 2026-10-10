<?php

use Tests\DatabaseGuard;

test('in-memory SQLite is always allowed', function () {
    expect(DatabaseGuard::refusal(['driver' => 'sqlite', 'database' => ':memory:']))->toBeNull();
});

test('a local PostgreSQL database ending in _test is allowed', function () {
    expect(DatabaseGuard::refusal(['driver' => 'pgsql', 'database' => 'nkwaledger_test', 'host' => '127.0.0.1']))->toBeNull();
});

test('a parallel worker database ending in _test_N is allowed', function () {
    expect(DatabaseGuard::refusal(['driver' => 'pgsql', 'database' => 'nkwaledger_test_3', 'host' => 'localhost']))->toBeNull();
});

test('the CI service host is allowed', function () {
    expect(DatabaseGuard::refusal(['driver' => 'pgsql', 'database' => 'ci_test', 'host' => 'postgres']))->toBeNull();
});

test('a database whose name does not end in _test is refused', function () {
    expect(DatabaseGuard::refusal(['driver' => 'pgsql', 'database' => 'nkwaledger', 'host' => '127.0.0.1']))
        ->toBe("Refusing to run the tests: the database name 'nkwaledger' does not end in _test.");
});

test('a name that only contains _test in the middle is refused', function () {
    expect(DatabaseGuard::refusal(['driver' => 'pgsql', 'database' => 'prod_test_data', 'host' => '127.0.0.1']))->not->toBeNull();
});

test('a remote host is refused even when the name ends in _test', function () {
    expect(DatabaseGuard::refusal(['driver' => 'pgsql', 'database' => 'nkwaledger_test', 'host' => 'db.railway.internal']))
        ->toBe("Refusing to run the tests: the database host 'db.railway.internal' is not this machine or the CI service.");
});
