<?php

namespace App\Auth;

use Illuminate\Validation\ValidationException;

// the one answer a locked account gets wherever it tries to sign in
class AccountLockedException extends ValidationException
{
    public const MESSAGE = 'Your account is locked. Please contact your agent.';

    public static function make(): static
    {
        // the sign-in page reads one field, the code page the other
        return static::withMessages(['identifier' => self::MESSAGE, 'code' => self::MESSAGE]);
    }
}
