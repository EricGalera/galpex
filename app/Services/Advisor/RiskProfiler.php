<?php

declare(strict_types=1);

namespace App\Services\Advisor;

use App\Exceptions\ValidationException;
use App\Support\Validator;

/**
 * Cuestionario financiero y cálculo del perfil del inversor.
 *
 * Se separan dos dimensiones, como hacen los test de idoneidad reales:
 *  - Tolerancia: cuánto riesgo QUIERE asumir (reacción ante caídas, pérdida máxima aceptable).
 *  - Capacidad: cuánto riesgo PUEDE asumir (horizonte, necesidad de liquidez, experiencia).
 * El puntaje final pondera más la menor de las dos.
 */
final class RiskProfiler
{
    public const QUESTIONS = [
        'objective' => [
            'label' => '¿Qué buscás principalmente con esta inversión?',
            'options' => [
                'preserve' => 'Proteger el capital de la inflación sin sobresaltos',
                'income' => 'Cobrar ingresos periódicos (cupones, dividendos)',
                'balanced' => 'Hacer crecer el capital con riesgo moderado',
                'growth' => 'Maximizar el crecimiento a largo plazo',
                'speculate' => 'Buscar ganancias altas aunque pueda perder bastante',
            ],
        ],
        'horizon' => [
            'label' => '¿En cuánto tiempo vas a necesitar este dinero?',
            'options' => ['lt1' => 'Menos de 1 año', '1to3' => 'Entre 1 y 3 años', '3to5' => 'Entre 3 y 5 años', '5to10' => 'Entre 5 y 10 años', 'gt10' => 'Más de 10 años'],
        ],
        'reaction' => [
            'label' => 'Si tu cartera cae 20% en tres meses, ¿qué hacés?',
            'options' => ['sell_all' => 'Vendo todo para no perder más', 'sell_part' => 'Vendo una parte', 'hold' => 'Espero a que se recupere', 'buy_more' => 'Aprovecho para comprar más'],
        ],
        'max_loss' => [
            'label' => '¿Qué pérdida anual estarías dispuesto a tolerar sin cambiar de estrategia?',
            'options' => ['5' => 'Hasta 5%', '10' => 'Hasta 10%', '20' => 'Hasta 20%', '30' => 'Hasta 30%', '40' => 'Más de 30%'],
        ],
        'experience' => [
            'label' => '¿Qué experiencia tenés invirtiendo?',
            'options' => ['none' => 'Ninguna', 'basic' => 'Plazos fijos, FCI o dólares', 'intermediate' => 'Acciones, bonos o CEDEARs', 'advanced' => 'Opero seguido distintos instrumentos, incluso derivados o cripto'],
        ],
        'liquidity' => [
            'label' => '¿Puede que necesites retirar parte del dinero de imprevisto?',
            'options' => ['high' => 'Sí, es probable', 'medium' => 'Quizás una parte chica', 'low' => 'No, tengo otro fondo de emergencia'],
        ],
        'income_pref' => [
            'label' => '¿Preferís cobrar rentas o reinvertir todo?',
            'options' => ['income' => 'Cobrar rentas periódicas', 'balanced' => 'Un poco de cada cosa', 'growth' => 'Reinvertir todo para crecer'],
        ],
        'markets' => [
            'label' => '¿Qué mercados te interesan? (podés elegir varios)',
            'multiple' => true,
            'options' => ['ar' => 'Argentina', 'us' => 'Estados Unidos', 'global' => 'Global / desarrollados', 'emerging' => 'Emergentes', 'tech' => 'Tecnología', 'crypto' => 'Criptomonedas'],
        ],
        'restrictions' => [
            'label' => '¿Hay algo que NO quieras en tu cartera?',
            'multiple' => true,
            'options' => ['no_crypto' => 'Criptomonedas', 'no_ars' => 'Activos en pesos', 'no_single_stocks' => 'Acciones individuales (solo fondos y ETFs)', 'no_ar_sovereign' => 'Bonos del Estado argentino'],
        ],
    ];

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed> perfil calculado
     * @throws ValidationException
     */
    public function evaluate(array $input): array
    {
        $rules = [
            'capital' => 'required|numeric|between:100,100000000000',
            'currency' => 'required|in:USD,ARS',
        ];
        foreach (self::QUESTIONS as $key => $q) {
            if (empty($q['multiple'])) {
                $rules[$key] = 'required|in:' . implode(',', array_map('strval', array_keys($q['options'])));
            }
        }
        $data = Validator::make($input, $rules);
        foreach (['markets', 'restrictions'] as $multi) {
            $values = is_array($input[$multi] ?? null) ? $input[$multi] : [];
            $data[$multi] = array_values(array_intersect(array_keys(self::QUESTIONS[$multi]['options']), $values));
        }
        if (in_array('crypto', $data['markets'], true) && in_array('no_crypto', $data['restrictions'], true)) {
            throw new ValidationException(['markets' => 'Marcaste interés en cripto y a la vez la excluiste. Elegí una de las dos.'], $input);
        }

        $reaction = ['sell_all' => 0, 'sell_part' => 30, 'hold' => 65, 'buy_more' => 100][$data['reaction']];
        $maxLoss = ['5' => 10, '10' => 30, '20' => 55, '30' => 75, '40' => 95][$data['max_loss']];
        $objective = ['preserve' => 10, 'income' => 35, 'balanced' => 55, 'growth' => 75, 'speculate' => 95][$data['objective']];
        $tolerance = (int) round(0.4 * $reaction + 0.4 * $maxLoss + 0.2 * $objective);

        $horizonPts = ['lt1' => 5, '1to3' => 25, '3to5' => 50, '5to10' => 75, 'gt10' => 95][$data['horizon']];
        $liquidityAdj = ['high' => -30, 'medium' => -10, 'low' => 0][$data['liquidity']];
        $experienceAdj = ['none' => -15, 'basic' => -5, 'intermediate' => 5, 'advanced' => 10][$data['experience']];
        $capacity = (int) max(0, min(100, $horizonPts + $liquidityAdj + $experienceAdj));

        $score = (int) round(0.6 * min($tolerance, $capacity) + 0.4 * (($tolerance + $capacity) / 2));
        $horizonYears = ['lt1' => 1, '1to3' => 2, '3to5' => 4, '5to10' => 7, 'gt10' => 15][$data['horizon']];

        return [
            'answers' => $data,
            'capital' => (float) $data['capital'],
            'currency' => $data['currency'],
            'horizon_years' => $horizonYears,
            'risk_tolerance' => $tolerance,
            'risk_capacity' => $capacity,
            'risk_score' => $score,
            'liquidity_need' => $data['liquidity'],
            'income_preference' => $data['income_pref'],
            'label' => self::label($score),
            'allocation' => self::strategicAllocation($score, $data),
        ];
    }

    public static function label(int $score): string
    {
        return match (true) {
            $score < 20 => 'Muy conservador',
            $score < 40 => 'Conservador',
            $score < 60 => 'Moderado',
            $score < 80 => 'Dinámico',
            default => 'Agresivo',
        };
    }

    /**
     * Asignación estratégica por clase de activo según el puntaje.
     *
     * @param array<string,mixed> $answers
     * @return array{equity:float,fixed_income:float,liquidity:float,crypto:float}
     */
    public static function strategicAllocation(int $score, array $answers): array
    {
        $liquidity = ['high' => 0.20, 'medium' => 0.10, 'low' => 0.03][$answers['liquidity']];
        if ($answers['horizon'] === 'lt1') {
            $liquidity = max($liquidity, 0.30);
        }
        $crypto = 0.0;
        $canCrypto = !in_array('no_crypto', $answers['restrictions'], true)
            && in_array($answers['experience'], ['intermediate', 'advanced'], true);
        if ($canCrypto && $score > 55) {
            $crypto = min(0.10, ($score - 55) / 45 * 0.10);
            if (in_array('crypto', $answers['markets'], true)) {
                $crypto = min(0.15, $crypto * 1.5 + 0.02);
            }
        }
        $equity = max(0.05, min(0.90, 0.10 + 0.85 * $score / 100));
        $equity = min($equity, 1 - $liquidity - $crypto);
        $fixed = max(0.0, 1 - $equity - $liquidity - $crypto);

        return ['equity' => round($equity, 4), 'fixed_income' => round($fixed, 4), 'liquidity' => round($liquidity, 4), 'crypto' => round($crypto, 4)];
    }
}
