<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthorizationException extends HttpException
{
    public function __construct(
        string $message = 'You are not authorized to perform this action.'
    ) {
        parent::__construct(403, $message);
    }
}
