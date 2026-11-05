<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Errores de validación de formulario, por campo. */
class ValidationException extends RuntimeException
{
    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $input
     */
    public function __construct(private array $errors, private array $input = [])
    {
        parent::__construct((string) reset($errors));
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string,mixed> */
    public function input(): array
    {
        $safe = $this->input;
        unset($safe['password'], $safe['password_confirmation'], $safe['current_password'], $safe['_token'], $safe['api_secret']);

        return $safe;
    }
}
