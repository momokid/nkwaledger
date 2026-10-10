<?php

namespace Tests;

// the suite wipes its database, so on anything but in-memory SQLite it only runs against a
// throwaway database: a name ending _test (or _test_N, which is what parallel workers use)
// on this machine or the CI service
class DatabaseGuard
{
    private const LOCAL_HOSTS = ['127.0.0.1', 'localhost', '::1', 'postgres'];

    /** @param array<string, mixed> $connection */
    public static function refusal(array $connection): ?string
    {
        if (($connection['driver'] ?? null) === 'sqlite') {
            return null;
        }

        $name = (string) ($connection['database'] ?? '');
        $host = (string) ($connection['host'] ?? '');

        if (! preg_match('/_test(_\d+)?$/', $name)) {
            return "Refusing to run the tests: the database name '{$name}' does not end in _test.";
        }

        if (! in_array($host, self::LOCAL_HOSTS, true)) {
            return "Refusing to run the tests: the database host '{$host}' is not this machine or the CI service.";
        }

        return null;
    }
}
