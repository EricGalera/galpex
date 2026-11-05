<?php

declare(strict_types=1);

namespace App\Services\Advisor;

use App\Models\Instrument;
use App\Services\Analytics\FinancialMath;
use App\Services\Analytics\RiskAnalyzer;
use App\Services\MarketData\MarketDataService;
use DateTimeImmutable;

/**
 * Motor de reglas que arma propuestas de cartera a partir del perfil.
 *
 * Cada estrategia define "bloques" (renta variable, renta fija, liquidez, cripto)
 * con instrumentos candidatos y pesos relativos. El perfil decide cuánto va a
 * cada bloque; las restricciones del usuario filtran instrumentos; y los datos
 * históricos se usan para estimar volatilidad y caída máxima de cada propuesta.
 *
 * Resultado: simulaciones educativas, no recomendaciones de inversión personalizadas.
 */
final class PortfolioBuilder
{
    /**
     * Estrategias: riesgo "natural" (0-100), inclinación de cada bloque y candidatos.
     * Candidato = [símbolo, mercado, peso relativo, rol].
     */
    private const STRATEGIES = [
        'preservation' => [
            'name' => 'Preservación de capital',
            'natural_risk' => 15,
            'tilt' => ['equity' => 0.45, 'crypto' => 0.0],
            'objectives' => ['preserve'],
            'equity' => [['SPY', 'NYSEARCA', 4, 'Exposición mínima a acciones, diversificada en las 500 mayores empresas de EE. UU.'], ['KO', 'NYSE', 1, 'Empresa defensiva con dividendos estables.'], ['JNJ', 'NYSE', 1, 'Salud: sector defensivo, menos sensible al ciclo.']],
            'fixed_income' => [['SHY', 'NASDAQ', 4, 'Bonos del Tesoro de corto plazo: baja volatilidad.'], ['VCSH', 'NASDAQ', 3, 'Bonos corporativos de corto plazo con algo más de rendimiento.'], ['TIP', 'NYSEARCA', 3, 'Protección contra la inflación en dólares.'], ['TX26', 'BYMA', 2, 'Bono en pesos ajustado por inflación (CER).']],
            'advantages' => ['Volatilidad baja y caídas acotadas.', 'Alta liquidez: casi todo se puede vender rápido.'],
            'limitations' => ['Rendimiento esperado bajo: puede quedar por debajo de la inflación en algunos años.', 'Poca participación en las subas del mercado.'],
        ],
        'income' => [
            'name' => 'Generación de ingresos',
            'natural_risk' => 35,
            'tilt' => ['equity' => 0.8, 'crypto' => 0.0],
            'objectives' => ['income'],
            'income' => true,
            'equity' => [['KO', 'NYSE', 2, 'Dividendo creciente por décadas.'], ['JNJ', 'NYSE', 2, 'Dividendo estable en el sector salud.'], ['XOM', 'NYSE', 2, 'Energía con dividendo alto.'], ['XLU', 'NYSEARCA', 2, 'Servicios públicos: flujos regulados y dividendos.'], ['V', 'NYSE', 1, 'Negocio de alta rentabilidad con dividendo en crecimiento.']],
            'fixed_income' => [['LQD', 'NYSEARCA', 3, 'Cupones de bonos corporativos de grado de inversión.'], ['HYG', 'NYSEARCA', 2, 'Mayor cupón a cambio de más riesgo de crédito.'], ['EMB', 'NASDAQ', 2, 'Deuda emergente en dólares con cupón alto.'], ['GD35', 'BYMA', 2, 'Bono soberano argentino con cupón creciente.'], ['YMCXO', 'BYMA', 2, 'ON de YPF: cupón en dólares de una empresa líder.'], ['PNDCO', 'BYMA', 1, 'ON de Pan American Energy en dólares.']],
            'advantages' => ['Flujo de fondos periódico (cupones y dividendos).', 'Menos dependencia de vender para obtener liquidez.'],
            'limitations' => ['Sensible a subas de tasas (los bonos bajan de precio).', 'Menor crecimiento del capital que una cartera de acciones.'],
        ],
        'fixed_income' => [
            'name' => 'Renta fija',
            'natural_risk' => 25,
            'tilt' => ['equity' => 0.0, 'crypto' => 0.0],
            'objectives' => ['preserve', 'income'],
            'equity' => [],
            'fixed_income' => [['AGG', 'NYSEARCA', 3, 'Bonos de grado de inversión de EE. UU. (núcleo).'], ['SHY', 'NASDAQ', 2, 'Tramo corto: amortigua subas de tasas.'], ['TIP', 'NYSEARCA', 2, 'Bonos ajustados por inflación.'], ['LQD', 'NYSEARCA', 2, 'Bonos corporativos para sumar rendimiento.'], ['GD30D', 'BYMA', 1, 'Soberano argentino de menor duración, en dólares.'], ['TLCMD', 'BYMA', 1, 'ON de Telecom Argentina en dólares.']],
            'advantages' => ['Resultados más previsibles.', 'Fuente de renta por cupones.'],
            'limitations' => ['Sin participación en el crecimiento de las empresas.', 'Riesgo de tasa: si suben, el valor de los bonos cae.'],
        ],
        'mixed' => [
            'name' => 'Estrategia mixta',
            'natural_risk' => 50,
            'tilt' => ['equity' => 1.0, 'crypto' => 0.8],
            'objectives' => ['balanced'],
            'equity' => [['SPY', 'NYSEARCA', 4, 'Núcleo de renta variable de EE. UU.'], ['VT', 'NYSEARCA', 2, 'Complemento global para no depender de un solo país.'], ['XLV', 'NYSEARCA', 1, 'Salud como sector defensivo.'], ['MELI', 'NASDAQ', 1, 'Crecimiento en América Latina (comercio y fintech).']],
            'fixed_income' => [['AGG', 'NYSEARCA', 3, 'Bonos de calidad para estabilizar.'], ['TIP', 'NYSEARCA', 1, 'Cobertura de inflación.'], ['GD35', 'BYMA', 1, 'Rendimiento extra con riesgo argentino acotado.']],
            'advantages' => ['Equilibrio entre crecimiento y estabilidad.', 'Diversificación entre clases de activo.'],
            'limitations' => ['En mercados alcistas fuertes rinde menos que una cartera 100% acciones.', 'Requiere rebalanceo periódico.'],
        ],
        'growth' => [
            'name' => 'Crecimiento de largo plazo',
            'natural_risk' => 65,
            'tilt' => ['equity' => 1.15, 'crypto' => 1.0],
            'objectives' => ['growth', 'balanced'],
            'equity' => [['SPY', 'NYSEARCA', 3, 'Base diversificada de grandes empresas.'], ['QQQ', 'NASDAQ', 2, 'Empresas de mayor crecimiento del Nasdaq.'], ['VT', 'NYSEARCA', 2, 'Diversificación global.'], ['MSFT', 'NASDAQ', 1, 'Líder en software y nube.'], ['AMZN', 'NASDAQ', 1, 'Comercio electrónico y nube.']],
            'fixed_income' => [['AGG', 'NYSEARCA', 2, 'Colchón para las caídas.'], ['SHY', 'NASDAQ', 1, 'Reserva de corto plazo para rebalancear.']],
            'advantages' => ['Mayor potencial de crecimiento en horizontes largos.', 'Diversificado en cientos de empresas a través de ETFs.'],
            'limitations' => ['Caídas temporales de 20% o más son posibles.', 'Necesita tiempo para compensar los años malos.'],
        ],
        'international' => [
            'name' => 'Diversificación internacional',
            'natural_risk' => 55,
            'tilt' => ['equity' => 1.05, 'crypto' => 0.6],
            'objectives' => ['balanced', 'growth'],
            'markets' => ['global', 'emerging'],
            'equity' => [['VT', 'NYSEARCA', 3, 'Mercado accionario mundial en un solo instrumento.'], ['URTH', 'NYSEARCA', 2, 'Países desarrollados (MSCI World).'], ['EEM', 'NYSEARCA', 2, 'Mercados emergentes.'], ['ASML', 'NASDAQ', 1, 'Europa: tecnología crítica para semiconductores.'], ['NVO', 'NYSE', 1, 'Dinamarca: salud con crecimiento.'], ['TM', 'NYSE', 1, 'Japón: industria automotriz.']],
            'fixed_income' => [['EMB', 'NASDAQ', 2, 'Deuda soberana emergente en dólares.'], ['AGG', 'NYSEARCA', 2, 'Bonos de EE. UU. de alta calidad.']],
            'advantages' => ['Reduce la dependencia de un solo país o moneda.', 'Acceso a sectores y empresas líderes fuera de EE. UU.'],
            'limitations' => ['Expuesta a riesgos cambiarios y políticos de varios países.', 'En la última década, fuera de EE. UU. el rendimiento fue menor.'],
        ],
        'tech' => [
            'name' => 'Exposición tecnológica',
            'natural_risk' => 85,
            'tilt' => ['equity' => 1.2, 'crypto' => 1.2],
            'objectives' => ['growth', 'speculate'],
            'markets' => ['tech'],
            'equity' => [['QQQ', 'NASDAQ', 3, 'Núcleo tecnológico diversificado.'], ['XLK', 'NYSEARCA', 2, 'Sector tecnológico del S&P 500.'], ['SMH', 'NASDAQ', 2, 'Semiconductores: infraestructura de la IA.'], ['NVDA', 'NASDAQ', 1, 'Líder en chips para inteligencia artificial.'], ['MSFT', 'NASDAQ', 1, 'Software y nube.'], ['TSM', 'NYSE', 1, 'Fabricación de chips a escala global.']],
            'fixed_income' => [['SHY', 'NASDAQ', 1, 'Liquidez para aprovechar caídas.']],
            'advantages' => ['Alto potencial en una tendencia de largo plazo.', 'Empresas con márgenes y crecimiento elevados.'],
            'limitations' => ['Muy concentrada en un sector: caídas de 30% o más son posibles.', 'Valuaciones exigentes y sensibles a tasas.'],
        ],
        'equity' => [
            'name' => 'Renta variable',
            'natural_risk' => 78,
            'tilt' => ['equity' => 1.3, 'crypto' => 1.0],
            'objectives' => ['growth', 'speculate'],
            'equity' => [['SPY', 'NYSEARCA', 3, 'Base del mercado de EE. UU.'], ['QQQ', 'NASDAQ', 2, 'Crecimiento tecnológico.'], ['IWM', 'NYSEARCA', 1, 'Empresas pequeñas, con más potencial y volatilidad.'], ['EEM', 'NYSEARCA', 1, 'Emergentes.'], ['JPM', 'NYSE', 1, 'Banca líder.'], ['XLE', 'NYSEARCA', 1, 'Energía como diversificación sectorial.']],
            'fixed_income' => [['SHY', 'NASDAQ', 1, 'Reserva mínima.']],
            'advantages' => ['Máxima participación en el crecimiento de las empresas.', 'Diversificada entre tamaños y sectores.'],
            'limitations' => ['Volatilidad alta.', 'No apta para quien necesite el dinero en el corto plazo.'],
        ],
        'argentina' => [
            'name' => 'Foco Argentina',
            'natural_risk' => 75,
            'tilt' => ['equity' => 1.0, 'crypto' => 0.5],
            'objectives' => ['growth', 'speculate', 'income'],
            'markets' => ['ar'],
            'equity' => [['GGAL', 'BYMA', 2, 'Banco líder: se beneficia del crecimiento del crédito.'], ['YPFD', 'BYMA', 2, 'Vaca Muerta: motor exportador de energía.'], ['PAMP', 'BYMA', 1, 'Energía integrada.'], ['ARGT', 'NYSEARCA', 2, 'Canasta diversificada de empresas argentinas en dólares.'], ['MELI', 'NASDAQ', 1, 'Empresa de origen argentino con negocio regional.']],
            'fixed_income' => [['GD30', 'BYMA', 2, 'Soberano corto ley Nueva York.'], ['GD35', 'BYMA', 2, 'Soberano largo con mayor rendimiento.'], ['YMCXO', 'BYMA', 1, 'ON de YPF.'], ['TLCMO', 'BYMA', 1, 'ON de Telecom Argentina.']],
            'advantages' => ['Potencial alto si mejora la macroeconomía local.', 'Cupones en dólares elevados en bonos y ONs.'],
            'limitations' => ['Riesgo país concentrado: política, tipo de cambio y regulaciones.', 'Volatilidad muy alta y correlación entre todos los activos locales.'],
        ],
    ];

    private const CRYPTO_SLEEVE = [['BTC', 'CRYPTO', 3, 'Bitcoin: el criptoactivo con más liquidez y trayectoria.'], ['ETH', 'CRYPTO', 2, 'Ethereum: infraestructura de contratos inteligentes.']];
    private const LIQUIDITY_USD = [['SHY', 'NASDAQ', 1, 'Liquidez en dólares: Tesoro de EE. UU. de 1 a 3 años.']];
    // Sin fuente pública de cuotapartes de FCI: la liquidez en pesos queda como efectivo de la cartera.
    private const LIQUIDITY_ARS = [];

    /** @var array<int,array<string,mixed>> */
    private array $instrumentCache = [];
    /** @var array<int,array<string,float>> */
    private array $seriesCache = [];

    public function __construct(private ?MarketDataService $market = null, private ?RiskAnalyzer $risk = null)
    {
        $this->market ??= new MarketDataService();
        $this->risk ??= new RiskAnalyzer($this->market);
    }

    /** @return list<string> */
    public static function strategyCodes(): array
    {
        return array_keys(self::STRATEGIES);
    }

    /**
     * Genera y ordena propuestas por afinidad con el perfil.
     *
     * @param array<string,mixed> $profile RiskProfiler::evaluate()
     * @return list<array<string,mixed>>
     */
    public function proposals(array $profile, int $max = 5): array
    {
        $answers = $profile['answers'];
        $scored = [];
        foreach (self::STRATEGIES as $code => $s) {
            $fit = $this->fit($code, $s, $profile);
            if ($fit === null) {
                continue;
            }
            $scored[$code] = $fit;
        }
        arsort($scored);
        $codes = array_slice(array_keys($scored), 0, $max - 1);

        $out = [];
        foreach ($codes as $code) {
            $p = $this->build($code, self::STRATEGIES[$code], $profile);
            if ($p !== null) {
                $p['fit_score'] = (int) round($scored[$code]);
                $out[] = $p;
            }
        }
        $custom = $this->buildCustom($profile);
        if ($custom !== null) {
            $out[] = $custom;
        }
        $this->addComparisons($out);

        return $out;
    }

    /**
     * Afinidad 0–100 entre estrategia y perfil (null = no aplica por restricciones).
     *
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $s
     */
    private function fit(string $code, array $s, array $profile): ?float
    {
        $a = $profile['answers'];
        if ($code === 'argentina' && (in_array('no_ars', $a['restrictions'], true) && in_array('no_ar_sovereign', $a['restrictions'], true))) {
            return null;
        }
        if (in_array($code, ['tech', 'equity'], true) && $profile['risk_score'] < 35) {
            return null; // demasiado riesgosa para el perfil
        }
        if ($code === 'preservation' && $profile['risk_score'] > 85) {
            return null;
        }
        $fit = 100 - abs($profile['risk_score'] - $s['natural_risk']) * 1.3;
        if (in_array($a['objective'], $s['objectives'], true)) {
            $fit += 12;
        }
        foreach ($s['markets'] ?? [] as $m) {
            if (in_array($m, $a['markets'], true)) {
                $fit += 15;
            }
        }
        if (!empty($s['income']) && $a['income_pref'] === 'income') {
            $fit += 10;
        }
        if (isset($s['markets']) && array_intersect($s['markets'], $a['markets']) === []) {
            $fit -= 20; // estrategia temática sin interés declarado
        }

        return max(1, min(100, $fit));
    }

    /**
     * @return array<string,mixed>|null
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $s
     */
    private function build(string $code, array $s, array $profile): ?array
    {
        $alloc = $profile['allocation'];
        $equity = $alloc['equity'] * $s['tilt']['equity'];
        $crypto = $alloc['crypto'] * $s['tilt']['crypto'];
        $liquidity = $alloc['liquidity'];
        $equity = min($equity, 1 - $liquidity - $crypto);
        $fixed = max(0, 1 - $equity - $crypto - $liquidity);

        $sleeves = [
            'equity' => [$equity, $s['equity'], 'Renta variable'],
            'fixed_income' => [$fixed, $s['fixed_income'], 'Renta fija'],
            'crypto' => [$crypto, self::CRYPTO_SLEEVE, 'Cripto'],
            'liquidity' => [$liquidity, $profile['currency'] === 'ARS' ? self::LIQUIDITY_ARS : self::LIQUIDITY_USD, 'Liquidez'],
        ];

        return $this->assemble($code, $s['name'], $sleeves, $profile, $s['advantages'], $s['limitations']);
    }

    /**
     * Estrategia personalizada a partir de los mercados elegidos por el usuario.
     *
     * @param array<string, mixed> $profile
     * @return array<string, mixed>
     */
    private function buildCustom(array $profile): ?array
    {
        $a = $profile['answers'];
        $map = [
            'ar' => [['GGAL', 'BYMA', 1, 'Interés declarado en Argentina.'], ['ARGT', 'NYSEARCA', 1, 'Argentina diversificada en dólares.']],
            'us' => [['SPY', 'NYSEARCA', 2, 'Interés declarado en EE. UU.']],
            'global' => [['VT', 'NYSEARCA', 2, 'Interés declarado en mercados globales.']],
            'emerging' => [['EEM', 'NYSEARCA', 1, 'Interés declarado en emergentes.'], ['EWZ', 'NYSEARCA', 1, 'Brasil, principal emergente de la región.']],
            'tech' => [['QQQ', 'NASDAQ', 1, 'Interés declarado en tecnología.'], ['SMH', 'NASDAQ', 1, 'Semiconductores.']],
        ];
        $equityCandidates = [];
        foreach ($a['markets'] as $m) {
            foreach ($map[$m] ?? [] as $c) {
                $equityCandidates[] = $c;
            }
        }
        if ($equityCandidates === []) {
            $equityCandidates = [['VT', 'NYSEARCA', 1, 'Sin preferencias declaradas: se usa el mercado global.']];
        }
        $fixedCandidates = [['AGG', 'NYSEARCA', 2, 'Estabilidad de bonos de alta calidad.'], ['TIP', 'NYSEARCA', 1, 'Protección contra inflación.']];
        if (in_array('ar', $a['markets'], true)) {
            $fixedCandidates[] = ['GD30', 'BYMA', 1, 'Bono soberano argentino en dólares.'];
        }
        $alloc = $profile['allocation'];
        $crypto = in_array('crypto', $a['markets'], true) ? max($alloc['crypto'], 0.03) : $alloc['crypto'];
        if (in_array('no_crypto', $a['restrictions'], true)) {
            $crypto = 0.0;
        }
        $equity = min($alloc['equity'], 1 - $alloc['liquidity'] - $crypto);
        $sleeves = [
            'equity' => [$equity, $equityCandidates, 'Renta variable'],
            'fixed_income' => [max(0, 1 - $equity - $crypto - $alloc['liquidity']), $fixedCandidates, 'Renta fija'],
            'crypto' => [$crypto, self::CRYPTO_SLEEVE, 'Cripto'],
            'liquidity' => [$alloc['liquidity'], $profile['currency'] === 'ARS' ? self::LIQUIDITY_ARS : self::LIQUIDITY_USD, 'Liquidez'],
        ];
        $p = $this->assemble(
            'custom',
            'Personalizada según tus mercados',
            $sleeves,
            $profile,
            ['Respeta exactamente los mercados que elegiste.', 'Mantiene la proporción de riesgo que surge de tu perfil.'],
            ['Al seguir tus preferencias puede quedar menos diversificada.', 'Conviene revisarla si cambian tus intereses o tu situación.'],
        );
        if ($p !== null) {
            $p['fit_score'] = 80;
        }

        return $p;
    }

    /**
     * Arma la asignación final filtrando restricciones, normalizando y midiendo riesgo histórico.
     *
     * @param array<string,array{0:float,1:list<array>,2:string}> $sleeves
     * @return array<string,mixed>|null
     * @param list<string> $advantages
     * @param list<string> $limitations
     * @param array<string, mixed> $profile
     */
    private function assemble(string $code, string $name, array $sleeves, array $profile, array $advantages, array $limitations): ?array
    {
        $restrictions = $profile['answers']['restrictions'];
        $weights = [];
        $reasons = [];
        $roles = [];
        $orphan = 0.0;

        foreach ($sleeves as $sleeve => [$share, $candidates, $label]) {
            if ($share <= 0) {
                continue;
            }
            $valid = [];
            foreach ($candidates as [$symbol, $market, $rel, $why]) {
                $inst = Instrument::findBySymbol($symbol, $market);
                if ($inst === null || (int) $inst['is_active'] !== 1 || !$this->allowed($inst, $restrictions)) {
                    continue;
                }
                $valid[] = [$inst, $rel, $why];
            }
            if ($valid === []) {
                $orphan += $share;
                continue;
            }
            $relSum = array_sum(array_map(fn($v) => $v[1], $valid));
            foreach ($valid as [$inst, $rel, $why]) {
                $id = (int) $inst['id'];
                $this->instrumentCache[$id] = $inst;
                $weights[$id] = ($weights[$id] ?? 0) + $share * $rel / $relSum;
                $reasons[$id] = $why;
                $roles[$id] = $label;
            }
        }
        if ($weights === []) {
            return null;
        }
        // El bloque que quedó sin instrumentos válidos se reparte proporcionalmente
        $total = array_sum($weights);
        foreach ($weights as $id => $w) {
            $weights[$id] = $w / $total;
        }
        unset($orphan);

        // Redondeo a 0,5 % y descarte de posiciones menores a 2 %
        $weights = array_filter($weights, fn($w) => $w >= 0.02);
        $total = array_sum($weights);
        $rounded = [];
        foreach ($weights as $id => $w) {
            $rounded[$id] = round($w / $total * 200) / 2; // en %
        }
        $diff = 100 - array_sum($rounded);
        arsort($rounded);
        $first = array_key_first($rounded);
        $rounded[$first] += $diff;

        $allocation = [];
        foreach ($rounded as $id => $pct) {
            $inst = $this->instrumentCache[$id];
            $allocation[] = [
                'instrument_id' => $id,
                'symbol' => $inst['symbol'],
                'name' => $inst['name'],
                'market' => $inst['market_code'],
                'category' => $inst['category_name'],
                'role' => $roles[$id],
                'weight' => $pct,
                'reason' => $reasons[$id],
            ];
        }

        $stats = $this->historicalStats($rounded, $profile['currency']);
        $classMix = [];
        foreach ($allocation as $row) {
            $classMix[$row['role']] = ($classMix[$row['role']] ?? 0) + $row['weight'];
        }

        $risks = $limitations;
        if ($stats['max_drawdown'] !== null && $stats['max_drawdown'] < -0.10) {
            $risks[] = sprintf('Con esta composición, en los últimos años la peor caída habría sido de %s.', pct($stats['max_drawdown'] * 100, 1));
        }
        if (($classMix['Renta variable'] ?? 0) > 60) {
            $risks[] = 'Más del 60% en renta variable: el valor puede oscilar mucho en el corto plazo.';
        }
        if (($classMix['Cripto'] ?? 0) > 0) {
            $risks[] = 'Incluye cripto: es el componente más volátil y puede perder gran parte de su valor.';
        }

        return [
            'code' => $code,
            'name' => $name,
            'allocation' => $allocation,
            'class_mix' => $classMix,
            'expected_return' => $stats['return'],
            'expected_volatility' => $stats['volatility'],
            'max_drawdown' => $stats['max_drawdown'],
            'stats_window' => $stats['window'],
            'analysis' => [
                'why' => 'La proporción entre bloques surge de tu perfil (' . $profile['label'] . ', puntaje ' . $profile['risk_score'] . '/100); los instrumentos, del enfoque "' . $name . '".',
                'horizon' => $this->horizonText($stats['volatility'], $profile['horizon_years']),
                'advantages' => $advantages,
                'risks' => $risks,
                'comparison' => [],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $inst
     * @param list<string> $restrictions
     */
    private function allowed(array $inst, array $restrictions): bool
    {
        if (in_array('no_crypto', $restrictions, true) && $inst['asset_class'] === 'crypto') {
            return false;
        }
        if (in_array('no_ars', $restrictions, true) && $inst['currency'] === 'ARS') {
            return false;
        }
        if (in_array('no_single_stocks', $restrictions, true) && in_array($inst['category_code'], ['stock_ar', 'stock_us', 'stock_intl', 'cedear'], true)) {
            return false;
        }
        if (in_array('no_ar_sovereign', $restrictions, true) && $inst['category_code'] === 'bond_sov' && $inst['country'] === 'AR') {
            return false;
        }

        return true;
    }

    /**
     * Rentabilidad y volatilidad HISTÓRICAS de la combinación (no son predicciones).
     *
     * @param array<int,float> $weights instrument_id => %
     * @return array{return:?float,volatility:?float,max_drawdown:?float,window:?string}
     */
    private function historicalStats(array $weights, string $currency): array
    {
        $to = date('Y-m-d');
        $from = (new DateTimeImmutable('-2 years'))->format('Y-m-d');
        $series = [];
        foreach ($weights as $id => $_) {
            if (!isset($this->seriesCache[$id])) {
                $this->seriesCache[$id] = $this->risk->seriesInBase($this->instrumentCache[$id], $from, $to, $currency);
            }
            $series[$id] = $this->seriesCache[$id];
        }
        $aligned = FinancialMath::align($series);
        if (count($aligned['dates']) < 60) {
            return ['return' => null, 'volatility' => null, 'max_drawdown' => null, 'window' => null];
        }
        $n = count($aligned['dates']) - 1;
        $port = array_fill(0, $n, 0.0);
        foreach ($aligned['series'] as $id => $values) {
            $r = FinancialMath::returns($values);
            foreach ($r as $t => $v) {
                $port[$t] += $weights[$id] / 100 * $v;
            }
        }
        $index = [100.0];
        foreach ($port as $r) {
            $index[] = end($index) * (1 + $r);
        }

        return [
            'return' => FinancialMath::annualizedReturn($port),
            'volatility' => FinancialMath::annualizedVolatility($port),
            'max_drawdown' => FinancialMath::maxDrawdown($index)['drawdown'],
            'window' => $aligned['dates'][0] . ' a ' . end($aligned['dates']),
        ];
    }

    private function horizonText(?float $vol, int $years): string
    {
        $min = match (true) {
            $vol === null => 3,
            $vol < 0.06 => 1,
            $vol < 0.10 => 3,
            $vol < 0.16 => 5,
            default => 7,
        };
        $text = "Horizonte mínimo sugerido: $min " . ($min === 1 ? 'año' : 'años') . '.';
        if ($years < $min) {
            $text .= " Tu horizonte declarado ($years " . ($years === 1 ? 'año' : 'años') . ') es más corto: hay más chances de tener que vender en un mal momento.';
        }

        return $text;
    }

    /** @param list<array<string,mixed>> $proposals */
    private function addComparisons(array &$proposals): void
    {
        foreach ($proposals as $i => &$p) {
            $lines = [];
            foreach ($proposals as $j => $other) {
                if ($i === $j || $p['expected_volatility'] === null || $other['expected_volatility'] === null) {
                    continue;
                }
                $dv = $p['expected_volatility'] - $other['expected_volatility'];
                if (abs($dv) < 0.01) {
                    $lines[] = sprintf('Riesgo similar a "%s".', $other['name']);
                } else {
                    $lines[] = sprintf(
                        '%s volátil que "%s" (%s contra %s anual).',
                        $dv > 0 ? 'Más' : 'Menos',
                        $other['name'],
                        pct($p['expected_volatility'] * 100, 1, false),
                        pct($other['expected_volatility'] * 100, 1, false),
                    );
                }
            }
            $p['analysis']['comparison'] = array_slice($lines, 0, 3);
        }
        unset($p);
    }
}
