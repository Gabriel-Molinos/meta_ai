<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;

class ApiException extends RuntimeException
{
    public function __construct(string $message, int $code = 0)
    {
        parent::__construct($message, $code);
    }
}
