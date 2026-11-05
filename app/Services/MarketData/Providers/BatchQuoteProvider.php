<?php

declare(strict_types=1);

namespace App\Services\MarketData\Providers;

/**
 * Proveedor que puede cotizar muchos instrumentos con pocas llamadas
 * (un panel completo de BYMA, 20 símbolos por consulta en Yahoo, etc.).
 */
interface BatchQuoteProvider
{
    /**
     * @param list<array<string,mixed>> $instruments
     * @return array<int,array<string,mixed>> id del instrumento => cotización (mismo formato que quote())
     */
    public function quoteMany(array $instruments): array;
}
