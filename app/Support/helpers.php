<?php

declare(strict_types=1);

/*
 * Funciones auxiliares globales para vistas y controladores.
 *
 * Laravel ya trae e() (escapa HTML contra XSS), config(), csrf_field(), csrf_token() y old():
 * se usan esas. Acá están solo las propias de GALPEX. app_url() y asset_url() tienen otro
 * nombre que url()/asset() de Laravel porque reciben parámetros distintos (consulta y versión).
 */


/** Carpeta donde vive la app, por ejemplo "/Galpex" en XAMPP (vacía si está en la raíz). */
function base_path_url(): string
{
    return app()->bound('request') ? rtrim(request()->getBaseUrl(), '/') : '';
}

/**
 * URL dentro de la app con parámetros de consulta: app_url('/mercados', ['q' => 'GGAL']).
 *
 * @param array<string, mixed> $query
 */
function app_url(string $path = '/', array $query = []): string
{
    $url = base_path_url() . '/' . ltrim($path, '/');
    if ($query !== []) {
        $url .= '?' . http_build_query($query);
    }

    return $url;
}

/** URL de un recurso estático con versión para evitar caché vieja. */
function asset_url(string $path): string
{
    $file = base_path('public/assets/' . ltrim($path, '/'));
    $v = is_file($file) ? (string) filemtime($file) : '1';

    return base_path_url() . '/assets/' . ltrim($path, '/') . '?v=' . $v;
}

/** Mensaje de error de validación para un campo. */
function field_error(string $key): ?string
{
    $errors = session()->get('form_errors', []);

    return is_array($errors) ? ($errors[$key] ?? null) : null;
}

/** Ícono del sistema de iconografía GALPEX (sprite SVG). */
function icon(string $name, string $class = 'icon'): string
{
    return '<svg class="' . e($class) . '" aria-hidden="true" focusable="false"><use href="'
        . e(asset_url('img/icons.svg')) . '#i-' . e($name) . '"></use></svg>';
}

/* ---------- Formato numérico (es-AR) ---------- */

function num(float|int|string|null $value, int $decimals = 2): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    return number_format((float) $value, $decimals, ',', '.');
}

/** Ajusta decimales según la magnitud (cripto chicas, índices grandes). */
function smart_decimals(float $value): int
{
    $abs = abs($value);

    return match (true) {
        $abs === 0.0 => 2,
        $abs < 0.01 => 6,
        $abs < 1 => 4,
        default => 2,
    };
}

function currency_symbol(string $currency): string
{
    return match (strtoupper($currency)) {
        'ARS' => '$',
        'USD', 'USDT', 'USDC' => 'US$',
        'EUR' => '€',
        'BRL' => 'R$',
        default => strtoupper($currency) . ' ',
    };
}

function money(float|int|string|null $value, string $currency = 'USD', ?int $decimals = null): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $v = (float) $value;
    $d = $decimals ?? smart_decimals($v);
    $sign = $v < 0 ? '−' : '';

    return $sign . currency_symbol($currency) . ' ' . number_format(abs($v), $d, ',', '.');
}

/** Monto con signo explícito (+/−) para resultados. */
function signed_money(float|int|string|null $value, string $currency = 'USD'): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $v = (float) $value;

    return ($v > 0 ? '+' : ($v < 0 ? '−' : '')) . currency_symbol($currency) . ' ' . number_format(abs($v), 2, ',', '.');
}

function pct(float|int|string|null $value, int $decimals = 2, bool $signed = true): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $v = (float) $value;
    $sign = $signed ? ($v > 0 ? '+' : ($v < 0 ? '−' : '')) : ($v < 0 ? '−' : '');

    return $sign . number_format(abs($v), $decimals, ',', '.') . '%';
}

/** Clase CSS según signo: "up", "down" o "flat". */
function trend_class(float|int|string|null $value): string
{
    $v = (float) ($value ?? 0);

    return $v > 0 ? 'up' : ($v < 0 ? 'down' : 'flat');
}

function compact_number(float|int|string|null $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $v = (float) $value;
    $abs = abs($v);

    return match (true) {
        $abs >= 1e12 => num($v / 1e12, 2) . ' B',   // billones (es)
        $abs >= 1e9 => num($v / 1e9, 2) . ' mil M',
        $abs >= 1e6 => num($v / 1e6, 2) . ' M',
        $abs >= 1e3 => num($v / 1e3, 1) . ' mil',
        default => num($v, 0),
    };
}

/* ---------- Fechas ---------- */

/** Convierte una fecha UTC de la base a la zona del usuario. */
function local_date(?string $utc, string $format = 'd/m/Y H:i'): string
{
    if ($utc === null || $utc === '') {
        return '—';
    }
    try {
        $d = new DateTimeImmutable($utc, new DateTimeZone('UTC'));

        return $d->setTimezone(new DateTimeZone(date_default_timezone_get()))->format($format);
    } catch (Throwable) {
        return $utc;
    }
}

function short_date(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $ts = strtotime($date);
    if ($ts === false) {
        return '—';
    }

    return date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

function time_ago(?string $utc): string
{
    if (!$utc) {
        return 'sin datos';
    }
    $diff = time() - (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->getTimestamp();

    return match (true) {
        $diff < 60 => 'hace instantes',
        $diff < 3600 => 'hace ' . intdiv($diff, 60) . ' min',
        $diff < 86400 => 'hace ' . intdiv($diff, 3600) . ' h',
        $diff < 86400 * 30 => 'hace ' . intdiv($diff, 86400) . ' d',
        default => local_date($utc, 'd/m/Y'),
    };
}

function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

/** Codifica datos para pasarlos al JavaScript sin scripts inline (CSP). */
function json_attr(mixed $data): string
{
    return e((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function is_active_nav(string $prefix): bool
{
    $current = \App\Http\View::shared('currentPath', '/');

    return $current === $prefix || str_starts_with((string) $current, rtrim($prefix, '/') . '/');
}

/**
 * Minigráfico SVG (sparkline) generado en el servidor.
 *
 * @param list<float> $values
 */
function sparkline(array $values, int $w = 84, int $h = 26, ?string $trend = null): string
{
    $values = array_values(array_filter($values, fn($v) => $v !== null));
    if (count($values) < 2) {
        return '<svg class="sparkline" viewBox="0 0 ' . $w . ' ' . $h . '" aria-hidden="true"></svg>';
    }
    $min = min($values);
    $max = max($values);
    $range = $max - $min ?: 1;
    $n = count($values) - 1;
    $pts = [];
    foreach ($values as $i => $v) {
        $pts[] = round($i / $n * ($w - 2) + 1, 1) . ',' . round($h - 2 - ($v - $min) / $range * ($h - 4), 1);
    }
    $trend ??= end($values) >= $values[0] ? 'up' : 'down';
    $color = $trend === 'up' ? 'var(--up)' : 'var(--down)';

    return '<svg class="sparkline" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" aria-hidden="true">'
        . '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="' . $color . '" stroke-width="1.5" stroke-linejoin="round" vector-effect="non-scaling-stroke"/></svg>';
}

/** Separa entero y centavos para destacar la parte entera en cifras grandes. */
function money_parts(float $value, string $currency): string
{
    $formatted = money($value, $currency, 2);
    $pos = strrpos($formatted, ',');
    if ($pos === false) {
        return e($formatted);
    }

    return e(substr($formatted, 0, $pos)) . '<span class="cents">' . e(substr($formatted, $pos)) . '</span>';
}
