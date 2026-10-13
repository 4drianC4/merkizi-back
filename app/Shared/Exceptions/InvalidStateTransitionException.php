<?php

namespace App\Shared\Exceptions;

class InvalidStateTransitionException extends DomainException
{
    public function __construct(string $message = 'Invalid state transition.')
    {
        parent::__construct($message, 'invalid_state', 409);
    }
}
