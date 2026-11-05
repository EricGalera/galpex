<?php

declare(strict_types=1);

/*
 * Catálogo base de mercados, categorías e instrumentos.
 *
 * Son los instrumentos "destacados", con nombre y descripción en castellano. El resto
 * del mercado (todo el panel de BYMA y las acciones de EE. UU.) se suma solo con
 * App\Services\Setup\CatalogSync a partir de los paneles públicos de data912.
 *
 * extra (opcional):
 *   yh       símbolo en Yahoo Finance ('' = Yahoo no lo cubre)
 *   d9       panel:símbolo en data912 (si difiere del ticker)
 *   dolar    casa de DolarApi / ArgentinaDatos (oficial, bolsa, blue...)
 *   byma     símbolo del índice en los datos abiertos de BYMA (MMSCI = MSCI Merval)
 *   bn       par en Binance        td / td_ex   símbolo y bolsa en Twelve Data      fh  símbolo en Finnhub
 *   unit     unidad de cotización (100 = precio cada 100 de valor nominal)
 *   frac     admite fracciones     benchmark    se ofrece como índice de comparación
 *   duration / coupon / maturity  datos de renta fija para los escenarios de tasas
 */

return [
    'markets' => [
        ['code' => 'BYMA',     'name' => 'Bolsas y Mercados Argentinos', 'country' => 'AR', 'currency' => 'ARS', 'timezone' => 'America/Argentina/Buenos_Aires', 'opens_at' => '11:00', 'closes_at' => '17:00'],
        ['code' => 'NYSE',     'name' => 'New York Stock Exchange',       'country' => 'US', 'currency' => 'USD', 'timezone' => 'America/New_York', 'opens_at' => '09:30', 'closes_at' => '16:00'],
        ['code' => 'NASDAQ',   'name' => 'Nasdaq',                        'country' => 'US', 'currency' => 'USD', 'timezone' => 'America/New_York', 'opens_at' => '09:30', 'closes_at' => '16:00'],
        ['code' => 'NYSEARCA', 'name' => 'NYSE Arca (ETFs)',              'country' => 'US', 'currency' => 'USD', 'timezone' => 'America/New_York', 'opens_at' => '09:30', 'closes_at' => '16:00'],
        ['code' => 'USA',      'name' => 'Bolsas de EE. UU.',             'country' => 'US', 'currency' => 'USD', 'timezone' => 'America/New_York', 'opens_at' => '09:30', 'closes_at' => '16:00'],
        ['code' => 'INDEX',    'name' => 'Índices',                       'country' => null, 'currency' => 'USD', 'timezone' => 'America/New_York', 'opens_at' => null, 'closes_at' => null],
        ['code' => 'FX',       'name' => 'Dólar y divisas',               'country' => null, 'currency' => 'USD', 'timezone' => 'UTC', 'opens_at' => null, 'closes_at' => null],
        ['code' => 'CRYPTO',   'name' => 'Cripto (24/7)',                 'country' => null, 'currency' => 'USD', 'timezone' => 'UTC', 'opens_at' => null, 'closes_at' => null],
    ],

    // code, name, asset_class, default_beta
    'categories' => [
        ['stock_ar',   'Acciones argentinas',         'equity',       1.20],
        ['cedear',     'CEDEARs',                     'equity',       1.00],
        ['bond_sov',   'Bonos públicos',              'fixed_income', 0.45],
        ['letra',      'Letras del Tesoro',           'fixed_income', 0.10],
        ['on',         'Obligaciones negociables',    'fixed_income', 0.30],
        ['stock_us',   'Acciones EE. UU.',            'equity',       1.00],
        ['adr',        'ADRs',                        'equity',       1.10],
        ['stock_intl', 'Acciones internacionales',    'equity',       0.95],
        ['etf_index',  'ETFs de índices',             'fund',         1.00],
        ['etf_sector', 'ETFs sectoriales',            'fund',         1.10],
        ['etf_intl',   'ETFs internacionales',        'fund',         0.90],
        ['etf_bond',   'ETFs de renta fija',          'fixed_income', 0.15],
        ['etf',        'Otros ETFs',                  'fund',         1.00],
        ['bond_corp',  'Bonos corporativos',          'fixed_income', 0.25],
        ['index',      'Índices bursátiles',          'index',        1.00],
        ['fx',         'Dólar y divisas',             'fx',           0.00],
        ['crypto',     'Criptomonedas',               'crypto',       1.60],
        ['stablecoin', 'Stablecoins',                 'crypto',       0.00],
    ],

    /*
     * symbol, name, market, category, currency, country, sector, extra
     */
    'instruments' => [

        // ── Acciones argentinas (BYMA, en pesos) ──
        ['GGAL', 'Grupo Financiero Galicia', 'BYMA', 'stock_ar', 'ARS', 'AR', 'Financiero', ['td' => 'GGAL', 'td_ex' => 'BCBA', 'desc' => 'Holding financiero dueño del Banco Galicia, uno de los mayores bancos privados de Argentina.']],
        ['YPFD', 'YPF S.A.', 'BYMA', 'stock_ar', 'ARS', 'AR', 'Energía', ['td' => 'YPFD', 'td_ex' => 'BCBA', 'desc' => 'Petrolera integrada con control estatal; principal operadora de Vaca Muerta.']],
        ['PAMP', 'Pampa Energía', 'BYMA', 'stock_ar', 'ARS', 'AR', 'Energía', ['td' => 'PAMP', 'td_ex' => 'BCBA', 'desc' => 'Empresa integrada de energía eléctrica y gas.']],
        ['BMA', 'Banco Macro', 'BYMA', 'stock_ar', 'ARS', 'AR', 'Financiero', ['td' => 'BMA', 'td_ex' => 'BCBA', 'desc' => 'Banco privado con fuerte presencia en el interior del país.']],
        ['TXAR', 'Ternium Argentina', 'BYMA', 'stock_ar', 'ARS', 'AR', 'Materiales', ['td' => 'TXAR', 'td_ex' => 'BCBA', 'desc' => 'Productora de acero plano.']],
        ['ALUA', 'Aluar Aluminio Argentino', 'BYMA', 'stock_ar', 'ARS', 'AR', 'Materiales', ['td' => 'ALUA', 'td_ex' => 'BCBA', 'desc' => 'Única productora de aluminio primario del país.']],
        ['CEPU', 'Central Puerto', 'BYMA', 'stock_ar', 'ARS', 'AR', 'Servicios públicos', ['td' => 'CEPU', 'td_ex' => 'BCBA', 'desc' => 'Generadora de energía eléctrica.']],
        ['TGSU2', 'Transportadora de Gas del Sur', 'BYMA', 'stock_ar', 'ARS', 'AR', 'Energía', ['td' => 'TGSU2', 'td_ex' => 'BCBA', 'desc' => 'Transporte de gas natural y procesamiento de líquidos.']],
        ['LOMA', 'Loma Negra', 'BYMA', 'stock_ar', 'ARS', 'AR', 'Materiales', ['td' => 'LOMA', 'td_ex' => 'BCBA', 'desc' => 'Mayor productora de cemento de Argentina.']],
        ['CRES', 'Cresud', 'BYMA', 'stock_ar', 'ARS', 'AR', 'Agro', ['td' => 'CRES', 'td_ex' => 'BCBA', 'desc' => 'Empresa agropecuaria y de real estate.']],
        ['BBAR', 'BBVA Argentina', 'BYMA', 'stock_ar', 'ARS', 'AR', 'Financiero', ['desc' => 'Banco BBVA en Argentina.']],
        ['SUPV', 'Grupo Supervielle', 'BYMA', 'stock_ar', 'ARS', 'AR', 'Financiero', ['desc' => 'Grupo financiero con banco, seguros y fintech.']],
        ['BYMA', 'Bolsas y Mercados Argentinos', 'BYMA', 'stock_ar', 'ARS', 'AR', 'Financiero', ['desc' => 'Operadora del mercado de capitales argentino.']],
        ['COME', 'Sociedad Comercial del Plata', 'BYMA', 'stock_ar', 'ARS', 'AR', 'Industria', ['desc' => 'Holding con negocios en energía, construcción y consumo.']],
        ['EDN', 'Edenor', 'BYMA', 'stock_ar', 'ARS', 'AR', 'Servicios públicos', ['desc' => 'Distribuidora de electricidad del norte del AMBA.']],
        ['TECO2', 'Telecom Argentina', 'BYMA', 'stock_ar', 'ARS', 'AR', 'Comunicaciones', ['desc' => 'Telefonía, internet y cable (Personal, Fibertel, Flow).']],
        ['TGNO4', 'Transportadora de Gas del Norte', 'BYMA', 'stock_ar', 'ARS', 'AR', 'Energía', ['desc' => 'Transporte de gas natural en el centro y norte del país.']],
        ['TRAN', 'Transener', 'BYMA', 'stock_ar', 'ARS', 'AR', 'Servicios públicos', ['desc' => 'Transporte de energía eléctrica en alta tensión.']],
        ['VALO', 'Grupo Financiero Valores', 'BYMA', 'stock_ar', 'ARS', 'AR', 'Financiero', ['desc' => 'Banco de Valores y servicios del mercado de capitales.']],
        ['IRSA', 'IRSA Inversiones y Representaciones', 'BYMA', 'stock_ar', 'ARS', 'AR', 'Inmobiliario', ['desc' => 'Centros comerciales, oficinas y hoteles.']],
        ['MIRG', 'Mirgor', 'BYMA', 'stock_ar', 'ARS', 'AR', 'Industria', ['desc' => 'Electrónica de consumo y autopartes.']],
        ['METR', 'Metrogas', 'BYMA', 'stock_ar', 'ARS', 'AR', 'Servicios públicos', ['desc' => 'Distribuidora de gas natural en el AMBA.']],

        // ── CEDEARs (BYMA, en pesos) ──
        ['AAPL', 'CEDEAR Apple (ratio 20:1)', 'BYMA', 'cedear', 'ARS', 'US', 'Tecnología', ['td' => 'AAPL', 'td_ex' => 'BCBA', 'desc' => 'Certificado de depósito argentino que representa acciones de Apple. 20 CEDEARs = 1 acción.']],
        ['MSFT', 'CEDEAR Microsoft (ratio 30:1)', 'BYMA', 'cedear', 'ARS', 'US', 'Tecnología', ['td' => 'MSFT', 'td_ex' => 'BCBA', 'desc' => 'CEDEAR de Microsoft. 30 CEDEARs = 1 acción.']],
        ['NVDA', 'CEDEAR NVIDIA (ratio 24:1)', 'BYMA', 'cedear', 'ARS', 'US', 'Tecnología', ['td' => 'NVDA', 'td_ex' => 'BCBA', 'desc' => 'CEDEAR de NVIDIA. 24 CEDEARs = 1 acción.']],
        ['KO', 'CEDEAR Coca-Cola (ratio 5:1)', 'BYMA', 'cedear', 'ARS', 'US', 'Consumo básico', ['td' => 'KO', 'td_ex' => 'BCBA', 'desc' => 'CEDEAR de Coca-Cola. 5 CEDEARs = 1 acción.']],
        ['MELI', 'CEDEAR MercadoLibre (ratio 120:1)', 'BYMA', 'cedear', 'ARS', 'AR', 'Consumo discrecional', ['td' => 'MELI', 'td_ex' => 'BCBA', 'desc' => 'CEDEAR de MercadoLibre. 120 CEDEARs = 1 acción.']],
        ['SPY', 'CEDEAR SPDR S&P 500 (ratio 20:1)', 'BYMA', 'cedear', 'ARS', 'US', 'Índice', ['td' => 'SPY', 'td_ex' => 'BCBA', 'desc' => 'CEDEAR del ETF que replica el S&P 500. 20 CEDEARs = 1 cuotaparte.']],
        ['GOOGL', 'CEDEAR Alphabet', 'BYMA', 'cedear', 'ARS', 'US', 'Comunicaciones', ['desc' => 'CEDEAR de Alphabet (Google).']],
        ['AMZN', 'CEDEAR Amazon', 'BYMA', 'cedear', 'ARS', 'US', 'Consumo discrecional', ['desc' => 'CEDEAR de Amazon.']],
        ['TSLA', 'CEDEAR Tesla', 'BYMA', 'cedear', 'ARS', 'US', 'Consumo discrecional', ['desc' => 'CEDEAR de Tesla.']],
        ['META', 'CEDEAR Meta Platforms', 'BYMA', 'cedear', 'ARS', 'US', 'Comunicaciones', ['desc' => 'CEDEAR de Meta (Facebook, Instagram, WhatsApp).']],
        ['QQQ', 'CEDEAR Invesco QQQ', 'BYMA', 'cedear', 'ARS', 'US', 'Índice', ['desc' => 'CEDEAR del ETF que replica el Nasdaq 100.']],
        ['VIST', 'CEDEAR Vista Energy', 'BYMA', 'cedear', 'ARS', 'AR', 'Energía', ['desc' => 'CEDEAR de Vista Energy, petrolera con foco en Vaca Muerta.']],
        ['GLD', 'CEDEAR SPDR Gold Shares', 'BYMA', 'cedear', 'ARS', 'US', 'Materias primas', ['desc' => 'CEDEAR del ETF que replica el precio del oro.']],
        ['EWZ', 'CEDEAR iShares MSCI Brazil', 'BYMA', 'cedear', 'ARS', 'BR', 'Índice', ['desc' => 'CEDEAR del ETF de acciones brasileñas.']],
        ['BRKB', 'CEDEAR Berkshire Hathaway', 'BYMA', 'cedear', 'ARS', 'US', 'Financiero', ['desc' => 'CEDEAR de Berkshire Hathaway (clase B).', 'yh' => 'BRKB.BA']],

        // ── Bonos soberanos (BYMA, cada 100 de valor nominal) ──
        ['AL30', 'Bonar 2030', 'BYMA', 'bond_sov', 'ARS', 'AR', 'Soberano', ['unit' => 100, 'desc' => 'Bono del Tesoro argentino, ley local, vencimiento 2030. Cotiza en pesos cada 100 de valor nominal; la especie terminada en D cotiza en dólares.', 'duration' => 2.3, 'coupon' => 0.0075, 'maturity' => '2030-07-09']],
        ['AL30D', 'Bonar 2030 (dólares)', 'BYMA', 'bond_sov', 'USD', 'AR', 'Soberano', ['unit' => 100, 'desc' => 'Misma especie que AL30, operada y liquidada en dólares MEP (cada 100 de valor nominal).', 'duration' => 2.3, 'coupon' => 0.0075, 'maturity' => '2030-07-09']],
        ['GD30', 'Global 2030', 'BYMA', 'bond_sov', 'ARS', 'AR', 'Soberano', ['unit' => 100, 'desc' => 'Bono global argentino, ley Nueva York, vencimiento 2030. Cotiza en pesos cada 100 de valor nominal; la especie terminada en D cotiza en dólares.', 'duration' => 2.3, 'coupon' => 0.0075, 'maturity' => '2030-07-09']],
        ['GD30D', 'Global 2030 (dólares)', 'BYMA', 'bond_sov', 'USD', 'AR', 'Soberano', ['unit' => 100, 'desc' => 'Misma especie que GD30, operada y liquidada en dólares MEP (cada 100 de valor nominal).', 'duration' => 2.3, 'coupon' => 0.0075, 'maturity' => '2030-07-09']],
        ['GD35', 'Global 2035', 'BYMA', 'bond_sov', 'ARS', 'AR', 'Soberano', ['unit' => 100, 'desc' => 'Bono global argentino, ley Nueva York, vencimiento 2035. Cotiza en pesos cada 100 de valor nominal; la especie terminada en D cotiza en dólares.', 'duration' => 6.2, 'coupon' => 0.0413, 'maturity' => '2035-07-09']],
        ['GD35D', 'Global 2035 (dólares)', 'BYMA', 'bond_sov', 'USD', 'AR', 'Soberano', ['unit' => 100, 'desc' => 'Misma especie que GD35, operada y liquidada en dólares MEP (cada 100 de valor nominal).', 'duration' => 6.2, 'coupon' => 0.0413, 'maturity' => '2035-07-09']],
        ['GD38', 'Global 2038', 'BYMA', 'bond_sov', 'ARS', 'AR', 'Soberano', ['unit' => 100, 'desc' => 'Bono global argentino, ley Nueva York, vencimiento 2038. Cotiza en pesos cada 100 de valor nominal; la especie terminada en D cotiza en dólares.', 'duration' => 5.4, 'coupon' => 0.05, 'maturity' => '2038-01-09']],
        ['GD38D', 'Global 2038 (dólares)', 'BYMA', 'bond_sov', 'USD', 'AR', 'Soberano', ['unit' => 100, 'desc' => 'Misma especie que GD38, operada y liquidada en dólares MEP (cada 100 de valor nominal).', 'duration' => 5.4, 'coupon' => 0.05, 'maturity' => '2038-01-09']],
        ['GD41', 'Global 2041', 'BYMA', 'bond_sov', 'ARS', 'AR', 'Soberano', ['unit' => 100, 'desc' => 'Bono global argentino, ley Nueva York, vencimiento 2041. Cotiza en pesos cada 100 de valor nominal; la especie terminada en D cotiza en dólares.', 'duration' => 7.1, 'coupon' => 0.0488, 'maturity' => '2041-07-09']],
        ['GD41D', 'Global 2041 (dólares)', 'BYMA', 'bond_sov', 'USD', 'AR', 'Soberano', ['unit' => 100, 'desc' => 'Misma especie que GD41, operada y liquidada en dólares MEP (cada 100 de valor nominal).', 'duration' => 7.1, 'coupon' => 0.0488, 'maturity' => '2041-07-09']],
        ['TX26', 'Boncer 2026 (CER)', 'BYMA', 'bond_sov', 'ARS', 'AR', 'Soberano', ['unit' => 100, 'desc' => 'Bono del Tesoro en pesos ajustado por inflación (CER). Cotiza en pesos cada 100 de valor nominal; la especie terminada en D cotiza en dólares.', 'duration' => 0.8, 'coupon' => 0.02, 'maturity' => '2026-11-09']],
        ['AL29', 'Bonar 2029', 'BYMA', 'bond_sov', 'ARS', 'AR', 'Soberano', ['unit' => 100, 'maturity' => '2029-07-09', 'desc' => 'Bono del Tesoro argentino, ley local, vencimiento 2029.']],
        ['AL35', 'Bonar 2035', 'BYMA', 'bond_sov', 'ARS', 'AR', 'Soberano', ['unit' => 100, 'maturity' => '2035-07-09', 'desc' => 'Bono del Tesoro argentino, ley local, vencimiento 2035.']],
        ['AE38', 'Bonar 2038', 'BYMA', 'bond_sov', 'ARS', 'AR', 'Soberano', ['unit' => 100, 'maturity' => '2038-01-09', 'desc' => 'Bono del Tesoro argentino, ley local, vencimiento 2038.']],
        ['AL41', 'Bonar 2041', 'BYMA', 'bond_sov', 'ARS', 'AR', 'Soberano', ['unit' => 100, 'maturity' => '2041-07-09', 'desc' => 'Bono del Tesoro argentino, ley local, vencimiento 2041.']],
        ['GD29', 'Global 2029', 'BYMA', 'bond_sov', 'ARS', 'AR', 'Soberano', ['unit' => 100, 'maturity' => '2029-07-09', 'desc' => 'Bono global argentino, ley Nueva York, vencimiento 2029.']],
        ['GD46', 'Global 2046', 'BYMA', 'bond_sov', 'ARS', 'AR', 'Soberano', ['unit' => 100, 'maturity' => '2046-07-09', 'desc' => 'Bono global argentino, ley Nueva York, vencimiento 2046.']],
        ['AL29D', 'Bonar 2029 (dólares)', 'BYMA', 'bond_sov', 'USD', 'AR', 'Soberano', ['unit' => 100, 'maturity' => '2029-07-09', 'desc' => 'Misma especie que AL29, operada y liquidada en dólares MEP (cada 100 de valor nominal).']],
        ['AL35D', 'Bonar 2035 (dólares)', 'BYMA', 'bond_sov', 'USD', 'AR', 'Soberano', ['unit' => 100, 'maturity' => '2035-07-09', 'desc' => 'Misma especie que AL35, operada y liquidada en dólares MEP (cada 100 de valor nominal).']],
        ['AE38D', 'Bonar 2038 (dólares)', 'BYMA', 'bond_sov', 'USD', 'AR', 'Soberano', ['unit' => 100, 'maturity' => '2038-01-09', 'desc' => 'Misma especie que AE38, operada y liquidada en dólares MEP (cada 100 de valor nominal).']],
        ['AL41D', 'Bonar 2041 (dólares)', 'BYMA', 'bond_sov', 'USD', 'AR', 'Soberano', ['unit' => 100, 'maturity' => '2041-07-09', 'desc' => 'Misma especie que AL41, operada y liquidada en dólares MEP (cada 100 de valor nominal).']],
        ['GD29D', 'Global 2029 (dólares)', 'BYMA', 'bond_sov', 'USD', 'AR', 'Soberano', ['unit' => 100, 'maturity' => '2029-07-09', 'desc' => 'Misma especie que GD29, operada y liquidada en dólares MEP (cada 100 de valor nominal).']],
        ['GD46D', 'Global 2046 (dólares)', 'BYMA', 'bond_sov', 'USD', 'AR', 'Soberano', ['unit' => 100, 'maturity' => '2046-07-09', 'desc' => 'Misma especie que GD46, operada y liquidada en dólares MEP (cada 100 de valor nominal).']],

        // ── Obligaciones negociables (BYMA, cada 100 de valor nominal) ──
        ['YMCXO', 'ON YPF 2031', 'BYMA', 'on', 'ARS', 'AR', 'Energía', ['unit' => 100, 'desc' => 'Obligación negociable de YPF S.A.. Cotiza en pesos cada 100 de valor nominal; la especie terminada en D cotiza en dólares.', 'duration' => 3.8, 'coupon' => 0.085]],
        ['YMCXD', 'ON YPF 2031 (dólares)', 'BYMA', 'on', 'USD', 'AR', 'Energía', ['unit' => 100, 'desc' => 'Misma especie que YMCXO, operada y liquidada en dólares MEP (cada 100 de valor nominal).', 'duration' => 3.8, 'coupon' => 0.085]],
        ['PNDCO', 'ON Pan American Energy', 'BYMA', 'on', 'ARS', 'AR', 'Energía', ['unit' => 100, 'desc' => 'Obligación negociable de Pan American Energy. Cotiza en pesos cada 100 de valor nominal; la especie terminada en D cotiza en dólares.', 'duration' => 2.5, 'coupon' => 0.0925]],
        ['PNDCD', 'ON Pan American Energy (dólares)', 'BYMA', 'on', 'USD', 'AR', 'Energía', ['unit' => 100, 'desc' => 'Misma especie que PNDCO, operada y liquidada en dólares MEP (cada 100 de valor nominal).', 'duration' => 2.5, 'coupon' => 0.0925]],
        ['TLCMO', 'ON Telecom Argentina clase M', 'BYMA', 'on', 'ARS', 'AR', 'Comunicaciones', ['unit' => 100, 'desc' => 'Obligación negociable de Telecom Argentina. Cotiza en pesos cada 100 de valor nominal; la especie terminada en D cotiza en dólares.']],
        ['TLCMD', 'ON Telecom Argentina clase M (dólares)', 'BYMA', 'on', 'USD', 'AR', 'Comunicaciones', ['unit' => 100, 'desc' => 'Misma especie que TLCMO, operada y liquidada en dólares MEP (cada 100 de valor nominal).']],

        // ── Acciones de EE. UU. ──
        ['AAPL', 'Apple Inc.', 'NASDAQ', 'stock_us', 'USD', 'US', 'Tecnología', ['fh' => 'AAPL', 'desc' => 'Fabricante de iPhone, Mac y servicios digitales.']],
        ['MSFT', 'Microsoft Corp.', 'NASDAQ', 'stock_us', 'USD', 'US', 'Tecnología', ['desc' => 'Software, nube (Azure) e inteligencia artificial.']],
        ['NVDA', 'NVIDIA Corp.', 'NASDAQ', 'stock_us', 'USD', 'US', 'Tecnología', ['desc' => 'Diseño de GPUs y chips para centros de datos e IA.']],
        ['AMZN', 'Amazon.com Inc.', 'NASDAQ', 'stock_us', 'USD', 'US', 'Consumo discrecional', ['desc' => 'Comercio electrónico y nube (AWS).']],
        ['GOOGL', 'Alphabet Inc. (Clase A)', 'NASDAQ', 'stock_us', 'USD', 'US', 'Comunicaciones', ['desc' => 'Google, YouTube y Google Cloud.']],
        ['META', 'Meta Platforms', 'NASDAQ', 'stock_us', 'USD', 'US', 'Comunicaciones', ['desc' => 'Facebook, Instagram y WhatsApp.']],
        ['TSLA', 'Tesla Inc.', 'NASDAQ', 'stock_us', 'USD', 'US', 'Consumo discrecional', ['desc' => 'Vehículos eléctricos y almacenamiento de energía.']],
        ['MELI', 'MercadoLibre Inc.', 'NASDAQ', 'stock_us', 'USD', 'AR', 'Consumo discrecional', ['desc' => 'Comercio electrónico y fintech (Mercado Pago) en América Latina.']],
        ['JPM', 'JPMorgan Chase & Co.', 'NYSE', 'stock_us', 'USD', 'US', 'Financiero', ['desc' => 'Mayor banco de EE. UU. por activos.']],
        ['V', 'Visa Inc.', 'NYSE', 'stock_us', 'USD', 'US', 'Financiero', ['desc' => 'Red global de pagos electrónicos.']],
        ['KO', 'The Coca-Cola Company', 'NYSE', 'stock_us', 'USD', 'US', 'Consumo básico', ['desc' => 'Bebidas no alcohólicas; dividendo creciente por décadas.']],
        ['JNJ', 'Johnson & Johnson', 'NYSE', 'stock_us', 'USD', 'US', 'Salud', ['desc' => 'Farmacéutica y dispositivos médicos.']],
        ['XOM', 'Exxon Mobil', 'NYSE', 'stock_us', 'USD', 'US', 'Energía', ['desc' => 'Petrolera integrada.']],
        ['WMT', 'Walmart Inc.', 'NYSE', 'stock_us', 'USD', 'US', 'Consumo básico', ['desc' => 'Cadena minorista más grande del mundo.']],

        // ── Acciones internacionales (ADRs) ──
        ['TSM', 'Taiwan Semiconductor (ADR)', 'NYSE', 'stock_intl', 'USD', 'TW', 'Tecnología', ['desc' => 'Mayor fabricante de semiconductores por contrato.']],
        ['ASML', 'ASML Holding (ADR)', 'NASDAQ', 'stock_intl', 'USD', 'NL', 'Tecnología', ['desc' => 'Equipos de litografía para la industria de chips.']],
        ['NVO', 'Novo Nordisk (ADR)', 'NYSE', 'stock_intl', 'USD', 'DK', 'Salud', ['desc' => 'Farmacéutica líder en diabetes y obesidad.']],
        ['SAP', 'SAP SE (ADR)', 'NYSE', 'stock_intl', 'USD', 'DE', 'Tecnología', ['desc' => 'Software de gestión empresarial.']],
        ['TM', 'Toyota Motor (ADR)', 'NYSE', 'stock_intl', 'USD', 'JP', 'Consumo discrecional', ['desc' => 'Automotriz japonesa.']],
        ['BABA', 'Alibaba Group (ADR)', 'NYSE', 'stock_intl', 'USD', 'CN', 'Consumo discrecional', ['desc' => 'Comercio electrónico y nube en China.']],
        ['VALE', 'Vale S.A. (ADR)', 'NYSE', 'stock_intl', 'USD', 'BR', 'Materiales', ['desc' => 'Minera brasileña, mayor productora de mineral de hierro.']],
        ['PBR', 'Petrobras (ADR)', 'NYSE', 'stock_intl', 'USD', 'BR', 'Energía', ['desc' => 'Petrolera estatal brasileña.']],

        // ── ETFs de índices ──
        ['SPY', 'SPDR S&P 500 ETF', 'NYSEARCA', 'etf_index', 'USD', 'US', 'Índice', ['desc' => 'Replica el índice S&P 500 (500 mayores empresas de EE. UU.).']],
        ['QQQ', 'Invesco QQQ Trust', 'NASDAQ', 'etf_index', 'USD', 'US', 'Índice', ['desc' => 'Replica el Nasdaq 100.']],
        ['DIA', 'SPDR Dow Jones Industrial Average ETF', 'NYSEARCA', 'etf_index', 'USD', 'US', 'Índice', ['desc' => 'Replica el Dow Jones Industrial Average.']],
        ['IWM', 'iShares Russell 2000 ETF', 'NYSEARCA', 'etf_index', 'USD', 'US', 'Índice', ['desc' => 'Empresas de baja capitalización de EE. UU.']],

        // ── ETFs internacionales ──
        ['URTH', 'iShares MSCI World ETF', 'NYSEARCA', 'etf_intl', 'USD', 'GL', 'Índice', ['desc' => 'Acciones de países desarrollados (réplica del MSCI World). Se usa como benchmark global.', 'benchmark' => true]],
        ['VT', 'Vanguard Total World Stock ETF', 'NYSEARCA', 'etf_intl', 'USD', 'GL', 'Índice', ['desc' => 'Mercado accionario global, desarrollados y emergentes.']],
        ['EEM', 'iShares MSCI Emerging Markets ETF', 'NYSEARCA', 'etf_intl', 'USD', 'EM', 'Índice', ['desc' => 'Acciones de mercados emergentes.']],
        ['EWZ', 'iShares MSCI Brazil ETF', 'NYSEARCA', 'etf_intl', 'USD', 'BR', 'Índice', ['desc' => 'Acciones brasileñas.']],
        ['ARGT', 'Global X MSCI Argentina ETF', 'NYSEARCA', 'etf_intl', 'USD', 'AR', 'Índice', ['desc' => 'Empresas argentinas y vinculadas a Argentina.']],

        // ── ETFs sectoriales ──
        ['XLK', 'Technology Select Sector SPDR', 'NYSEARCA', 'etf_sector', 'USD', 'US', 'Tecnología', ['desc' => 'Sector tecnológico del S&P 500.']],
        ['XLF', 'Financial Select Sector SPDR', 'NYSEARCA', 'etf_sector', 'USD', 'US', 'Financiero', ['desc' => 'Sector financiero del S&P 500.']],
        ['XLE', 'Energy Select Sector SPDR', 'NYSEARCA', 'etf_sector', 'USD', 'US', 'Energía', ['desc' => 'Sector energético del S&P 500.']],
        ['XLV', 'Health Care Select Sector SPDR', 'NYSEARCA', 'etf_sector', 'USD', 'US', 'Salud', ['desc' => 'Sector salud del S&P 500.']],
        ['XLU', 'Utilities Select Sector SPDR', 'NYSEARCA', 'etf_sector', 'USD', 'US', 'Servicios públicos', ['desc' => 'Servicios públicos (electricidad, gas, agua).']],
        ['SMH', 'VanEck Semiconductor ETF', 'NASDAQ', 'etf_sector', 'USD', 'US', 'Tecnología', ['desc' => 'Empresas de semiconductores.']],

        // ── ETFs de renta fija ──
        ['AGG', 'iShares Core U.S. Aggregate Bond ETF', 'NYSEARCA', 'etf_bond', 'USD', 'US', 'Renta fija', ['desc' => 'Bonos de grado de inversión de EE. UU.', 'duration' => 6.0, 'coupon' => 0.038]],
        ['BND', 'Vanguard Total Bond Market ETF', 'NASDAQ', 'etf_bond', 'USD', 'US', 'Renta fija', ['desc' => 'Mercado total de bonos de EE. UU.', 'duration' => 6.1, 'coupon' => 0.037]],
        ['TLT', 'iShares 20+ Year Treasury Bond ETF', 'NASDAQ', 'etf_bond', 'USD', 'US', 'Renta fija', ['desc' => 'Bonos del Tesoro de EE. UU. a más de 20 años.', 'duration' => 16.5, 'coupon' => 0.042]],
        ['SHY', 'iShares 1-3 Year Treasury Bond ETF', 'NASDAQ', 'etf_bond', 'USD', 'US', 'Renta fija', ['desc' => 'Bonos del Tesoro de corto plazo (1 a 3 años).', 'duration' => 1.9, 'coupon' => 0.04]],
        ['TIP', 'iShares TIPS Bond ETF', 'NYSEARCA', 'etf_bond', 'USD', 'US', 'Renta fija', ['desc' => 'Bonos del Tesoro ajustados por inflación.', 'duration' => 6.7, 'coupon' => 0.025]],
        ['HYG', 'iShares iBoxx High Yield Corporate Bond ETF', 'NYSEARCA', 'etf_bond', 'USD', 'US', 'Renta fija', ['desc' => 'Bonos corporativos de alto rendimiento.', 'duration' => 3.4, 'coupon' => 0.065]],
        ['EMB', 'iShares J.P. Morgan USD Emerging Markets Bond ETF', 'NASDAQ', 'etf_bond', 'USD', 'EM', 'Renta fija', ['desc' => 'Deuda soberana emergente en dólares.', 'duration' => 7.0, 'coupon' => 0.055]],

        // ── Bonos corporativos de EE. UU. (vía ETF) ──
        ['LQD', 'iShares iBoxx Investment Grade Corporate Bond ETF', 'NYSEARCA', 'bond_corp', 'USD', 'US', 'Renta fija', ['desc' => 'Bonos corporativos de grado de inversión de EE. UU.', 'duration' => 8.3, 'coupon' => 0.045]],
        ['VCSH', 'Vanguard Short-Term Corporate Bond ETF', 'NASDAQ', 'bond_corp', 'USD', 'US', 'Renta fija', ['desc' => 'Bonos corporativos de corto plazo.', 'duration' => 2.6, 'coupon' => 0.04]],

        // ── Índices ──
        ['SPX', 'S&P 500', 'INDEX', 'index', 'USD', 'US', 'Índice', ['benchmark' => true, 'desc' => 'Índice de las 500 mayores empresas de EE. UU.', 'yh' => '^GSPC']],
        ['NDX', 'Nasdaq 100', 'INDEX', 'index', 'USD', 'US', 'Índice', ['benchmark' => true, 'desc' => 'Las 100 mayores empresas no financieras del Nasdaq.', 'yh' => '^NDX']],
        ['DJI', 'Dow Jones Industrial Average', 'INDEX', 'index', 'USD', 'US', 'Índice', ['benchmark' => true, 'desc' => '30 grandes empresas industriales de EE. UU.', 'yh' => '^DJI']],
        ['MERV', 'MSCI Merval', 'INDEX', 'index', 'ARS', 'AR', 'Índice', ['yh' => '', 'byma' => 'MMSCI', 'benchmark' => true, 'desc' => 'Índice de referencia de la bolsa argentina desde octubre de 2026 (reemplazó al S&P Merval), en pesos. Fuente: BYMA.']],
        ['GMSCI', 'MSCI BYMA General', 'INDEX', 'index', 'ARS', 'AR', 'Índice', ['yh' => '', 'byma' => 'GMSCI', 'desc' => 'Índice amplio de las acciones argentinas que cotizan en BYMA, en pesos. Fuente: BYMA.']],
        ['IBOV', 'Ibovespa', 'INDEX', 'index', 'BRL', 'BR', 'Índice', ['desc' => 'Principal índice de la bolsa de Brasil.', 'yh' => '^BVSP']],
        ['IXIC', 'Nasdaq Composite', 'INDEX', 'index', 'USD', 'US', 'Índice', ['yh' => '^IXIC', 'desc' => 'Todas las acciones que cotizan en el Nasdaq.']],
        ['RUT', 'Russell 2000', 'INDEX', 'index', 'USD', 'US', 'Índice', ['yh' => '^RUT', 'desc' => 'Las 2000 empresas de menor capitalización del Russell 3000.']],
        ['VIX', 'Índice de volatilidad CBOE (VIX)', 'INDEX', 'index', 'USD', 'US', 'Índice', ['yh' => '^VIX', 'desc' => 'Mide la volatilidad esperada del S&P 500 a 30 días: sube cuando el mercado tiene miedo.']],
        ['STOXX50E', 'Euro Stoxx 50', 'INDEX', 'index', 'EUR', 'EU', 'Índice', ['yh' => '^STOXX50E', 'desc' => 'Las 50 mayores empresas de la zona euro.']],
        ['N225', 'Nikkei 225', 'INDEX', 'index', 'JPY', 'JP', 'Índice', ['yh' => '^N225', 'desc' => 'Principal índice de la bolsa de Tokio.']],
        ['FTSE', 'FTSE 100', 'INDEX', 'index', 'GBP', 'GB', 'Índice', ['yh' => '^FTSE', 'desc' => 'Las 100 mayores empresas de la bolsa de Londres.']],

        // ── Dólar y divisas ──
        ['USD/ARS', 'Dólar MEP (bolsa)', 'FX', 'fx', 'ARS', null, 'Divisas', ['frac' => true, 'dolar' => 'bolsa', 'yh' => '', 'desc' => 'Dólar que surge de comprar un bono en pesos y venderlo en dólares (AL30 / AL30D). Es el tipo de cambio que GALPEX usa para convertir entre pesos y dólares.']],
        ['EUR/USD', 'Euro / Dólar estadounidense', 'FX', 'fx', 'USD', null, 'Divisas', ['frac' => true, 'desc' => 'Par de divisas más operado del mundo.', 'yh' => 'EURUSD=X']],
        ['USD/BRL', 'Dólar estadounidense / Real brasileño', 'FX', 'fx', 'BRL', null, 'Divisas', ['frac' => true, 'desc' => 'Tipo de cambio dólar-real.', 'yh' => 'BRL=X']],
        ['USD/ARS-OF', 'Dólar oficial (Banco Nación)', 'FX', 'fx', 'ARS', 'AR', 'Divisas', ['frac' => true, 'dolar' => 'oficial', 'yh' => '', 'desc' => 'Cotización minorista del Banco de la Nación Argentina.']],
        ['USD/ARS-MAY', 'Dólar mayorista', 'FX', 'fx', 'ARS', 'AR', 'Divisas', ['frac' => true, 'dolar' => 'mayorista', 'yh' => '', 'desc' => 'Tipo de cambio entre bancos (Comunicación A 3500 del BCRA).']],
        ['USD/ARS-CCL', 'Dólar contado con liquidación', 'FX', 'fx', 'ARS', 'AR', 'Divisas', ['frac' => true, 'dolar' => 'contadoconliqui', 'yh' => '', 'desc' => 'Dólar que surge de comprar activos en pesos en Argentina y venderlos en dólares en el exterior.']],
        ['USD/ARS-BLUE', 'Dólar blue', 'FX', 'fx', 'ARS', 'AR', 'Divisas', ['frac' => true, 'dolar' => 'blue', 'yh' => '', 'desc' => 'Cotización del mercado informal.']],
        ['USD/ARS-CRIPTO', 'Dólar cripto', 'FX', 'fx', 'ARS', 'AR', 'Divisas', ['frac' => true, 'dolar' => 'cripto', 'yh' => '', 'desc' => 'Precio de las stablecoins en dólares en exchanges locales.']],
        ['USD/ARS-TARJ', 'Dólar tarjeta', 'FX', 'fx', 'ARS', 'AR', 'Divisas', ['frac' => true, 'dolar' => 'tarjeta', 'yh' => '', 'desc' => 'Dólar oficial con los recargos que se aplican a consumos con tarjeta en el exterior.']],
        ['EUR/ARS', 'Euro / Peso argentino', 'FX', 'fx', 'ARS', null, 'Divisas', ['frac' => true, 'yh' => 'EURARS=X', 'desc' => 'Tipo de cambio euro-peso (referencia internacional).']],
        ['GBP/USD', 'Libra esterlina / Dólar', 'FX', 'fx', 'USD', null, 'Divisas', ['frac' => true, 'yh' => 'GBPUSD=X', 'desc' => 'Tipo de cambio libra-dólar.']],
        ['USD/JPY', 'Dólar / Yen japonés', 'FX', 'fx', 'JPY', null, 'Divisas', ['frac' => true, 'yh' => 'JPY=X', 'desc' => 'Tipo de cambio dólar-yen.']],

        // ── Criptomonedas ──
        ['BTC', 'Bitcoin', 'CRYPTO', 'crypto', 'USD', null, 'Cripto', ['bn' => 'BTCUSDT', 'td' => 'BTC/USD', 'frac' => true, 'desc' => 'Primera criptomoneda; oferta máxima de 21 millones.']],
        ['ETH', 'Ethereum', 'CRYPTO', 'crypto', 'USD', null, 'Cripto', ['bn' => 'ETHUSDT', 'td' => 'ETH/USD', 'frac' => true, 'desc' => 'Plataforma de contratos inteligentes.']],
        ['SOL', 'Solana', 'CRYPTO', 'crypto', 'USD', null, 'Cripto', ['bn' => 'SOLUSDT', 'td' => 'SOL/USD', 'frac' => true, 'desc' => 'Blockchain de alto rendimiento.']],
        ['BNB', 'BNB', 'CRYPTO', 'crypto', 'USD', null, 'Cripto', ['bn' => 'BNBUSDT', 'frac' => true, 'desc' => 'Token de la red BNB Chain.']],
        ['XRP', 'XRP', 'CRYPTO', 'crypto', 'USD', null, 'Cripto', ['bn' => 'XRPUSDT', 'frac' => true, 'desc' => 'Red de pagos XRP Ledger.']],
        ['ADA', 'Cardano', 'CRYPTO', 'crypto', 'USD', null, 'Cripto', ['bn' => 'ADAUSDT', 'frac' => true, 'desc' => 'Blockchain de prueba de participación.']],
        ['DOGE', 'Dogecoin', 'CRYPTO', 'crypto', 'USD', null, 'Cripto', ['bn' => 'DOGEUSDT', 'frac' => true, 'desc' => 'Criptomoneda de origen humorístico.']],
        ['LINK', 'Chainlink', 'CRYPTO', 'crypto', 'USD', null, 'Cripto', ['bn' => 'LINKUSDT', 'frac' => true, 'desc' => 'Red de oráculos descentralizados.']],
        ['AVAX', 'Avalanche', 'CRYPTO', 'crypto', 'USD', null, 'Cripto', ['bn' => 'AVAXUSDT', 'frac' => true, 'desc' => 'Blockchain de contratos inteligentes con subredes.']],
        ['DOT', 'Polkadot', 'CRYPTO', 'crypto', 'USD', null, 'Cripto', ['bn' => 'DOTUSDT', 'frac' => true, 'desc' => 'Red que conecta blockchains independientes.']],
        ['LTC', 'Litecoin', 'CRYPTO', 'crypto', 'USD', null, 'Cripto', ['bn' => 'LTCUSDT', 'frac' => true, 'desc' => 'Criptomoneda de pagos derivada de Bitcoin.']],
        ['TRX', 'TRON', 'CRYPTO', 'crypto', 'USD', null, 'Cripto', ['bn' => 'TRXUSDT', 'frac' => true, 'desc' => 'Blockchain muy usada para transferir stablecoins.']],
        ['BCH', 'Bitcoin Cash', 'CRYPTO', 'crypto', 'USD', null, 'Cripto', ['bn' => 'BCHUSDT', 'frac' => true, 'desc' => 'Bifurcación de Bitcoin orientada a pagos.']],
        ['XLM', 'Stellar', 'CRYPTO', 'crypto', 'USD', null, 'Cripto', ['bn' => 'XLMUSDT', 'frac' => true, 'desc' => 'Red de pagos internacionales.']],
        ['ATOM', 'Cosmos', 'CRYPTO', 'crypto', 'USD', null, 'Cripto', ['bn' => 'ATOMUSDT', 'frac' => true, 'desc' => 'Ecosistema de blockchains interoperables.']],
        ['UNI', 'Uniswap', 'CRYPTO', 'crypto', 'USD', null, 'Cripto', ['bn' => 'UNIUSDT', 'frac' => true, 'desc' => 'Token del mayor exchange descentralizado.']],
        ['NEAR', 'NEAR Protocol', 'CRYPTO', 'crypto', 'USD', null, 'Cripto', ['bn' => 'NEARUSDT', 'frac' => true, 'desc' => 'Blockchain de contratos inteligentes.']],
        ['SHIB', 'Shiba Inu', 'CRYPTO', 'crypto', 'USD', null, 'Cripto', ['bn' => 'SHIBUSDT', 'frac' => true, 'desc' => 'Token meme del ecosistema Ethereum.']],
        ['SUI', 'Sui', 'CRYPTO', 'crypto', 'USD', null, 'Cripto', ['bn' => 'SUIUSDT', 'frac' => true, 'desc' => 'Blockchain de alto rendimiento.']],
        ['POL', 'Polygon (POL)', 'CRYPTO', 'crypto', 'USD', null, 'Cripto', ['bn' => 'POLUSDT', 'frac' => true, 'yh' => 'POL28321-USD', 'desc' => 'Red de escalado para Ethereum (antes MATIC).']],

        // ── Stablecoins ──
        ['USDT', 'Tether', 'CRYPTO', 'stablecoin', 'USD', null, 'Stablecoin', ['frac' => true, 'desc' => 'Stablecoin atada al dólar estadounidense.', 'yh' => 'USDT-USD']],
        ['USDC', 'USD Coin', 'CRYPTO', 'stablecoin', 'USD', null, 'Stablecoin', ['bn' => 'USDCUSDT', 'frac' => true, 'desc' => 'Stablecoin respaldada por reservas en dólares.', 'yh' => 'USDC-USD']],
    ],

    // code, name, requests_per_minute, requests_per_day
    'providers' => [
        ['data912',      'data912 (BYMA)',            100,  50000],
        ['yahoo',        'Yahoo Finance',             60,   20000],
        ['dolar',        'DolarApi / ArgentinaDatos', 60,   20000],
        ['byma',         'BYMA Datos abiertos',       30,   20000],
        ['binance',      'Binance (datos públicos)',  1200, 1000000],
        ['twelvedata',   'Twelve Data',               8,    800],
        ['finnhub',      'Finnhub',                   60,   100000],
        ['alphavantage', 'Alpha Vantage',             5,    25],
    ],
];
