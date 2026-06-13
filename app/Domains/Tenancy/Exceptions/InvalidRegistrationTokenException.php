<?php

namespace App\Domains\Tenancy\Exceptions;

use RuntimeException;

class InvalidRegistrationTokenException extends RuntimeException
{
    public static function expiredOrInvalid(): self
    {
        return new self('This verification link is invalid or has expired. Please sign up again.');
    }

    public static function slugTaken(): self
    {
        return new self('This workspace URL was just taken. Please sign up again with a different URL.');
    }
}
