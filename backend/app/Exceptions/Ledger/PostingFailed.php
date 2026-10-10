<?php

namespace App\Exceptions\Ledger;

use RuntimeException;

class PostingFailed extends RuntimeException
{
    private bool $system = false;

    // every refusal wears the same face, so one catch block covers them all
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    // not the record's fault (a database failure), so the same record may be tried again
    public static function system(string $message): self
    {
        $failure = new self($message);
        $failure->system = true;

        return $failure;
    }

    public function isSystem(): bool
    {
        return $this->system;
    }
}
