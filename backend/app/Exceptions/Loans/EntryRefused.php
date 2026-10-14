<?php

namespace App\Exceptions\Loans;

use App\Support\Wording;
use RuntimeException;

// a refusal that belongs on the screen as it is: a wrong code, a code that ran out, an entry that has moved on
class EntryRefused extends RuntimeException
{
    public static function because(string $words): self
    {
        return new self(Wording::resolve($words));
    }
}
