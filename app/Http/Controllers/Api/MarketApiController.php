<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\HttpException;
use App\Http\Controllers\Controller;
use App\Http\Request;
use App\Http\Response;
use App\Models\Instrument;
use App\Models\Notification;
use App\Services\MarketData\MarketDataService;
use DateTimeImmutable;

/**
 * API JSON de mercado: sugerencias del buscador, cotizaciones, franja de referencias e historial de precios.
 */
final class MarketApiController extends Controller
{
    private MarketDataService $market;

    public function __construct()
    {
        $this->market = new MarketDataService();
    }

    /**
     * @return array<string,mixed>
     * @param array<string, mixed> $i
     */
    public static function present(array $i): array
    {
        return [
            'id' => (int) $i['id'],
            'symbol' => $i['symbol'],
            'name' => $i['name'],
            'market' => $i['market_code'],
            'category' => $i['category_name'],
            'currency' => $i['currency'],
            'price' => $i['price'] !== null ? (float) $i['price'] : null,
            'change_pct' => $i['change_pct'] !== null ? (float) $i['change_pct'] : null,
            'status' => MarketDataService::dataStatus($i),
            'quoted_at' => $i['quoted_at'],
            'source' => $i['quote_source'],
        ];
    }

    public function search(Request $request): Response
    {
        $q = trim(preg_replace('/\s+/', ' ', (string) $request->query('q', '')) ?? '');
        if ($q === '' || mb_strlen($q) > 40) {
            return $this->json(['results' => []]);
        }
        $rows = Instrument::search(['q' => $q], 10, 'relevance');
        if ($this->market->completeNames($rows, 1) > 0) {
            $rows = Instrument::search(['q' => $q], 10, 'relevance');
        }
        $rows = $this->market->refreshQuotes($rows);

        return $this->json(['results' => array_map([self::class, 'present'], $rows)]);
    }

    /** Cotizaciones actualizadas de una lista de ids (máx. 40). */
    public function quotes(Request $request): Response
    {
        $ids = array_slice(array_filter(array_map('intval', explode(',', (string) $request->query('ids', '')))), 0, 40);
        $items = $this->market->refreshQuotes(array_values(Instrument::findMany($ids)));

        return $this->json(['items' => array_map([self::class, 'present'], $items)]);
    }

    public function tape(Request $request): Response
    {
        $items = $this->market->refreshQuotes(self::tapeInstruments());

        return $this->json([
            'items' => array_map([self::class, 'present'], $items),
            'unread' => Notification::unreadCount($this->userId()),
        ]);
    }

    public function history(Request $request): Response
    {
        $inst = Instrument::find((int) $request->param('id'));
        if ($inst === null) {
            throw new HttpException(404, 'Instrumento inexistente.');
        }
        $range = strtoupper((string) $request->query('rango', '1A'));
        $days = ['1S' => 7, '1M' => 31, '3M' => 92, '6M' => 183, '1A' => 366, '3A' => 1100, '5A' => 1830, 'MAX' => 4000][$range] ?? 366;
        $from = (new DateTimeImmutable("-$days days"))->format('Y-m-d');
        $bars = $this->market->bars($inst, $from, date('Y-m-d'));
        $inst = $this->market->refreshQuote($inst);
        $today = date('Y-m-d');
        if ($inst['price'] !== null && (end($bars)['date'] ?? '') < $today) {
            $bars[] = ['date' => $today, 'open' => $inst['open_price'], 'high' => $inst['day_high'], 'low' => $inst['day_low'],
                'close' => $inst['price'], 'volume' => $inst['volume'], 'live' => true];
        }

        // Comparación opcional contra otro instrumento (base 100)
        $compare = null;
        if ($request->query('vs')) {
            $other = Instrument::find((int) $request->query('vs'));
            if ($other) {
                $closes = $this->market->closes($other, $from, $today);
                $compare = ['symbol' => $other['name'], 'points' => array_map(fn($d, $v) => ['date' => $d, 'close' => $v], array_keys($closes), $closes)];
            }
        }

        return $this->json([
            'instrument' => self::present($inst),
            'bars' => array_map(fn($b) => [
                'date' => $b['date'], 'open' => $b['open'] !== null ? (float) $b['open'] : null, 'high' => $b['high'] !== null ? (float) $b['high'] : null,
                'low' => $b['low'] !== null ? (float) $b['low'] : null, 'close' => (float) $b['close'], 'volume' => $b['volume'] !== null ? (float) $b['volume'] : null,
            ], $bars),
            'compare' => $compare,
        ]);
    }
}
