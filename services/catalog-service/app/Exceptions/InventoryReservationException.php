<?php

namespace App\Exceptions;

use RuntimeException;

class InventoryReservationException extends RuntimeException
{

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(
        string $message,
        public readonly int $httpStatus = 409,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
