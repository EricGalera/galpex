<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Violación de una regla de negocio (fondos insuficientes, venta mayor a la tenencia...).
 * El mensaje se muestra tal cual al usuario.
 */
class BusinessRuleException extends RuntimeException {}
