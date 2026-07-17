<?php

namespace App\Exceptions;

use RuntimeException;

class InventoryReservationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 409,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
