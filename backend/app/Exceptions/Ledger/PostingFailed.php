<?php

namespace App\Exceptions\Ledger;

use RuntimeException;

class PostingFailed extends RuntimeException
{
    // every refusal wears the same face, so one catch block covers them all
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
