<?php

namespace App\Shared\Exceptions;

class InsufficientPaymentException extends DomainException
{
    public function __construct(string $message = 'Confirmed payments do not cover the order total.')
    {
        parent::__construct($message, 'insufficient_payment', 422);
    }
}
