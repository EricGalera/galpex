<?php

declare(strict_types=1);

/*
 * Tabla de comisiones del bróker.
 *
 * La comisión no la elige el usuario: depende del tipo de instrumento, como en
 * cualquier bróker real. Se cobra sobre el importe de cada compra y cada venta.
 * Valores de referencia de los brókers argentinos (sin IVA ni derechos de mercado).
 */
return [
    // Grupos tal como se muestran al usuario: título => [tasa, categorías del catálogo]
    'groups' => [
        'Acciones y CEDEARs' => [0.0050, ['stock_ar', 'cedear']],
        'Acciones y ADRs de EE. UU.' => [0.0050, ['stock_us', 'adr', 'stock_intl']],
        'ETFs' => [0.0050, ['etf_index', 'etf_sector', 'etf_intl', 'etf_bond', 'etf', 'bond_corp']],
        'Bonos públicos' => [0.0025, ['bond_sov']],
        'Obligaciones negociables' => [0.0025, ['on']],
        'Letras del Tesoro' => [0.0015, ['letra']],
        'Criptomonedas' => [0.0060, ['crypto']],
        'Stablecoins' => [0.0020, ['stablecoin']],
    ],

    // Tasa para una categoría que no figure arriba
    'default' => 0.0050,

    // Comisión mínima por operación, en la moneda de la cartera (0 = sin mínimo)
    'minimum' => 0.0,
];
