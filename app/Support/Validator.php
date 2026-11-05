<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ValidationException;
use App\Security\SecurityPolicy;

/**
 * Validación de entradas del lado del servidor.
 *
 * Reglas: required, nullable, string, email, numeric, integer, min:n, max:n,
 * between:a,b, in:a,b,c, date, confirmed, regex:/.../, positive, symbol.
 *
 * Ejemplo:
 *   $data = Validator::make($input, ['email' => 'required|email', 'monto' => 'required|numeric|min:1']);
 */
final class Validator
{
    /** @var array<string,string> */
    private const LABELS = [
        'name' => 'nombre', 'email' => 'email', 'password' => 'contraseña',
        'quantity' => 'cantidad', 'amount' => 'monto', 'price' => 'precio',
        'initial_capital' => 'capital inicial', 'base_currency' => 'moneda base',
        'threshold' => 'valor', 'start_date' => 'fecha inicial', 'end_date' => 'fecha final',
    ];

    /**
     * @param array<string,mixed> $input
     * @param array<string,string> $rules
     * @return array<string,mixed> datos validados y normalizados
     * @throws ValidationException
     */
    public static function make(array $input, array $rules): array
    {
        $errors = [];
        $clean = [];

        foreach ($rules as $field => $ruleString) {
            $value = $input[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            $ruleList = explode('|', $ruleString);
            $nullable = in_array('nullable', $ruleList, true);
            $empty = $value === null || $value === '' || $value === [];

            if ($empty) {
                if (in_array('required', $ruleList, true)) {
                    $errors[$field] = 'Completá el campo ' . self::label($field) . '.';
                } elseif ($nullable || !in_array('required', $ruleList, true)) {
                    $clean[$field] = null;
                }
                continue;
            }

            $isNumericField = (bool) array_intersect(['numeric', 'integer', 'positive'], $ruleList);
            if ($isNumericField && is_string($value)) {
                $value = self::normalizeNumber($value);
            }

            // Un campo simple no puede llegar como lista (name[]=…): se rechaza en vez de fallar más adelante.
            if (is_array($value) && !in_array('array', $ruleList, true)) {
                $errors[$field] = 'El valor de ' . self::label($field) . ' no es válido.';
                continue;
            }

            foreach ($ruleList as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $error = self::check($name, $arg, $value, $field, $input);
                if ($error !== null) {
                    $errors[$field] = $error;
                    break;
                }
            }

            if (!isset($errors[$field])) {
                if (in_array('integer', $ruleList, true)) {
                    $value = (int) $value;
                } elseif ($isNumericField) {
                    $value = (float) $value;
                }
                $clean[$field] = $value;
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors, $input);
        }

        return $clean;
    }

    /**
     * Acepta formato argentino ("1.234,56", "25.000") y punto decimal ("1234.56", "0.5").
     * Un punto seguido de grupos de exactamente tres cifras se toma como separador de miles.
     */
    public static function normalizeNumber(string $value): string
    {
        $value = str_replace([' ', "\u{00A0}", '$', 'US'], '', $value);
        if (!str_contains($value, ',') && preg_match('/^-?[1-9]\d{0,2}(\.\d{3})+$/', $value)) {
            return str_replace('.', '', $value);
        }
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $input
     */
    private static function check(string $rule, ?string $arg, mixed $value, string $field, array $input): ?string
    {
        $label = self::label($field);

        return match ($rule) {
            'required', 'nullable' => null,
            'string' => is_string($value) ? null : "El campo $label debe ser texto.",
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Ingresá un email válido.',
            'numeric' => is_numeric($value) && is_finite((float) $value) ? null : "El campo $label debe ser un número.",
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false ? null : "El campo $label debe ser un número entero.",
            'positive' => is_numeric($value) && (float) $value > 0 ? null : "El campo $label debe ser mayor que cero.",
            'min' => self::size($value) >= (float) $arg ? null : (is_numeric($value)
                ? "El campo $label debe ser al menos $arg."
                : "El campo $label debe tener al menos $arg caracteres."),
            'max' => self::size($value) <= (float) $arg ? null : (is_numeric($value)
                ? "El campo $label no puede superar $arg."
                : "El campo $label no puede superar $arg caracteres."),
            'between' => (function () use ($arg, $value, $label) {
                [$a, $b] = array_map('floatval', explode(',', (string) $arg));
                $s = self::size($value);

                return $s >= $a && $s <= $b ? null : "El campo $label debe estar entre $a y $b.";
            })(),
            'in' => in_array((string) $value, explode(',', (string) $arg), true) ? null : "El valor de $label no es válido.",
            'date' => self::isDate((string) $value) ? null : "El campo $label debe ser una fecha (AAAA-MM-DD).",
            'confirmed' => ($input[$field . '_confirmation'] ?? null) === $value ? null : 'Las contraseñas no coinciden.',
            'regex' => preg_match((string) $arg, (string) $value) ? null : "El formato de $label no es válido.",
            'password' => SecurityPolicy::passwordProblem((string) $value),
            default => null,
        };
    }

    private static function size(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (is_array($value)) {
            return count($value);
        }

        return mb_strlen((string) $value);
    }

    public static function isDate(string $value): bool
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $d !== false && $d->format('Y-m-d') === $value;
    }

    private static function label(string $field): string
    {
        return self::LABELS[$field] ?? str_replace('_', ' ', $field);
    }
}
