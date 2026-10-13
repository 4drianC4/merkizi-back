<?php

namespace App\Shared\Exceptions;

class LimitExceededException extends DomainException
{
    public function __construct(string $limitKey, int $max)
    {
        parent::__construct(
            "Plan limit reached for '{$limitKey}' (maximum {$max}).",
            'limit_exceeded',
            422,
        );
    }
}
