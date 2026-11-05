<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Error HTTP con código de estado (404, 403, 405, 429...). */
class HttpException extends RuntimeException
{
    public function __construct(private int $statusCode, string $message = '')
    {
        parent::__construct($message, $statusCode);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
