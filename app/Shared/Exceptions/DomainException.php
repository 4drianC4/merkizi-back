<?php

namespace App\Shared\Exceptions;

use RuntimeException;

/**
 * Base class for expected business-rule failures. Rendered as a structured
 * JSON error with a stable machine code and an HTTP status.
 */
class DomainException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'domain_error',
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }
}
