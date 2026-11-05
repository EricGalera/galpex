<?php

declare(strict_types=1);

namespace App\Services\News;

use App\Services\MarketData\ApiUsage;
use App\Services\MarketData\Cache;
use App\Services\MarketData\HttpClient;
use App\Services\MarketData\Providers\FinnhubProvider;
use DateTimeImmutable;
use Throwable;

/**
 * Noticias financieras reales en castellano desde Google Noticias (RSS público, sin clave).
 * Cada noticia lleva el nombre del medio y el enlace a la nota original.
 *
 * Si además hay una clave de Finnhub, se suma el calendario de presentación de resultados.
 */
class NewsService
{
    private const RSS = 'https://news.google.com/rss/search';

    /** Tema => búsqueda en Google Noticias. */
    public const TOPICS = [
        'mercados' => ['Mercado local', '(Merval OR "acciones argentinas" OR CEDEAR OR "bonos argentinos" OR "riesgo país")'],
        'economia' => ['Economía', '(dólar OR inflación OR BCRA OR "tasa de interés") Argentina'],
        'mundo' => ['Wall Street', '("Wall Street" OR "S&P 500" OR Nasdaq OR "Reserva Federal")'],
        'cripto' => ['Cripto', '(bitcoin OR ethereum OR criptomonedas)'],
    ];

    public function __construct(
        private ?HttpClient $http = null,
        private ?FinnhubProvider $finnhub = null,
    ) {
        $this->http ??= new HttpClient();
        $this->finnhub ??= new FinnhubProvider();
    }

    /** Google Noticias no requiere configuración. */
    public function available(): bool
    {
        return true;
    }

    /** @return array{items:list<array<string,mixed>>,error:?string} */
    public function market(string $topic = 'mercados'): array
    {
        $query = self::TOPICS[$topic][1] ?? self::TOPICS['mercados'][1];

        return $this->search($query, 3);
    }

    /**
     * Noticias de una empresa o activo, buscadas por su nombre.
     *
     * @return array{items:list<array<string,mixed>>,error:?string}
     */
    public function about(string $name, string $symbol = '', int $days = 7): array
    {
        $clean = self::searchName($name);
        if ($clean === '') {
            return ['items' => [], 'error' => null];
        }
        $query = '"' . $clean . '"' . ($symbol !== '' && preg_match('/^[A-Z]{2,5}$/', $symbol) ? " OR $symbol" : '');

        return $this->search($query, $days);
    }

    /** Nombre apto para buscar: sin "CEDEAR", ratios, clase ni forma societaria. */
    public static function searchName(string $name): string
    {
        $n = preg_replace('/^CEDEAR\s+/i', '', $name) ?? $name;
        $n = preg_replace('/\s*\(.*?\)\s*/', ' ', $n) ?? $n;
        $n = preg_replace('/[,.]?\s+(S\.?A\.?(I\.?C\.?)?|Inc\.?|Corp\.?|Corporation|Ltd\.?|plc|N\.V\.|S\.E\.|SE|Co\.|Company|Holdings?|Group|Clase [A-Z])\b\.?/i', ' ', $n) ?? $n;

        return trim(preg_replace('/\s+/', ' ', $n) ?? $n);
    }

    /** @return array{items:list<array<string,mixed>>,error:?string} */
    public function search(string $query, int $days = 3): array
    {
        $key = 'news:' . md5($query . $days);
        try {
            $items = Cache::remember($key, 900, fn() => $this->fetch($query . " when:{$days}d"));
        } catch (Throwable $e) {
            ApiUsage::failure('google-news', $e->getMessage());

            return ['items' => [], 'error' => 'No se pudieron obtener las noticias: ' . $e->getMessage()];
        }

        return ['items' => $items ?? [], 'error' => null];
    }

    /** @return list<array<string,mixed>> */
    private function fetch(string $query): array
    {
        $xml = $this->http->get(self::RSS, ['q' => $query, 'hl' => 'es-419', 'gl' => 'AR', 'ceid' => 'AR:es-419'], ['Accept' => 'application/rss+xml'], 'google-news');
        if (!function_exists('simplexml_load_string')) {
            throw new \RuntimeException('La extensión SimpleXML de PHP no está habilitada.');
        }
        $prev = libxml_use_internal_errors(true);
        // LIBXML_NONET: el XML no puede hacer que el servidor descargue nada (XXE); sin entidades externas.
        $rss = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
        libxml_use_internal_errors($prev);
        if ($rss === false || !isset($rss->channel)) {
            throw new \RuntimeException('Respuesta inválida de Google Noticias.');
        }

        $items = [];
        $seen = [];
        foreach ($rss->channel->item as $item) {
            $title = trim((string) $item->title);
            $source = trim((string) $item->source);
            $url = trim((string) $item->link);
            if ($title === '' || !str_starts_with($url, 'https://')) {
                continue;
            }
            // Google agrega " - Medio" al final del título
            if ($source !== '' && str_ends_with($title, ' - ' . $source)) {
                $title = mb_substr($title, 0, mb_strlen($title) - mb_strlen($source) - 3);
            }
            // Algunos ítems traen solo el nombre del medio como título: no son noticias
            if (mb_strlen($title) < 15 || mb_strtolower($title) === mb_strtolower($source)) {
                continue;
            }
            $dedupe = mb_strtolower(mb_substr($title, 0, 60));
            if (isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;
            $time = strtotime((string) $item->pubDate) ?: time();
            $items[] = [
                'id' => md5($url),
                'headline' => html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'summary' => '',
                'source' => $source !== '' ? $source : 'Google Noticias',
                'source_url' => (string) ($item->source['url'] ?? ''),
                'url' => $url,
                'image' => '',
                'datetime' => gmdate('Y-m-d H:i:s', $time),
                'related' => [],
            ];
        }
        usort($items, fn($a, $b) => strcmp($b['datetime'], $a['datetime']));

        return array_slice($items, 0, 40);
    }

    /** ¿Hay clave de Finnhub para el calendario de resultados? */
    public function earningsAvailable(): bool
    {
        return $this->finnhub->isConfigured();
    }

    /**
     * Calendario de presentación de resultados (solo con clave de Finnhub).
     *
     * @param list<string> $symbols si se indica, filtra por esos símbolos
     * @return array{items:list<array<string,mixed>>,error:?string}
     */
    public function earnings(array $symbols = [], int $days = 21): array
    {
        if (!$this->earningsAvailable()) {
            return ['items' => [], 'error' => null];
        }
        try {
            $data = Cache::remember('earnings:' . date('Y-m-d'), 6 * 3600, function () use ($days) {
                if (!ApiUsage::acquire('finnhub')) {
                    return null;
                }
                $r = $this->finnhub->get('/calendar/earnings', ['from' => date('Y-m-d'), 'to' => (new DateTimeImmutable("+$days days"))->format('Y-m-d')]);
                ApiUsage::success('finnhub');

                return $r['earningsCalendar'] ?? [];
            }) ?? [];
        } catch (Throwable $e) {
            ApiUsage::failure('finnhub', $e->getMessage());

            return ['items' => [], 'error' => 'No se pudo obtener el calendario: ' . $e->getMessage()];
        }
        if ($symbols !== []) {
            $data = array_values(array_filter($data, fn($e) => in_array($e['symbol'] ?? '', $symbols, true)));
        }
        usort($data, fn($a, $b) => strcmp((string) $a['date'], (string) $b['date']));

        return ['items' => array_slice($data, 0, 60), 'error' => null];
    }
}
