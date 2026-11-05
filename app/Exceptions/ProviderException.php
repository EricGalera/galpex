<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Falla de un proveedor externo de datos (red, límite, símbolo no soportado). */
class ProviderException extends RuntimeException
{
    public function __construct(string $message, private string $provider = '', private bool $rateLimited = false)
    {
        parent::__construct($message);
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function isRateLimited(): bool
    {
        return $this->rateLimited;
    }
}
