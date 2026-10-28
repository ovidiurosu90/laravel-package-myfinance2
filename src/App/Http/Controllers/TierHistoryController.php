<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use ovidiuro\myfinance2\App\Models\Currency;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;
use ovidiuro\myfinance2\App\Services\PerformanceDataset;
use ovidiuro\myfinance2\App\Services\StoredCloses;
use ovidiuro\myfinance2\App\Services\TierCalculationService;
use ovidiuro\myfinance2\App\Services\SymbolPerformanceService;
use ovidiuro\myfinance2\App\Services\TierHistoryPresenter;
use ovidiuro\myfinance2\App\Services\TierHistoryRepository;

/**
 * Data behind the tier history chart on /watchlist-symbols: every stored day of one symbol's tier
 * history with its basis value and tier, the daily closes over the same span, and the runs and
 * per-tier totals the modal lists under the chart.
 */
class TierHistoryController extends MyFinance2Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate(['symbol' => 'required|string|max:20']);
        $symbol    = $validated['symbol'];
        $userId    = (int) auth()->id();
        $history   = (new TierHistoryRepository())->loadSymbol($userId, $symbol);
        $from      = !empty($history)
            ? (string) array_key_first($history)
            : (string) config('myfinance2.watchlistsymbols.tier_history.start_date');

        return response()->json((new TierHistoryPresenter())->forChart(
            $symbol,
            $history,
            $this->_quotes($userId, $symbol, $from),
            Carbon::today(),
            (int) config('myfinance2.watchlistsymbols.tier_history.lookback_days', 365),
            $this->_holdings($userId, $symbol)
        ));
    }

    /**
     * Your trades in the symbol, your holding periods (oldest first) and your overall yearly return on it,
     * for the hover lines, the B / S markers and the "Your holdings" chart and table.
     */
    private function _holdings(int $userId, string $symbol): array
    {
        $perf    = (new SymbolPerformanceService())->handle($userId)[$symbol] ?? null;
        $trades  = $this->_trades($userId, $symbol);
        $windows = [];
        foreach ($perf['windows'] ?? [] as $w) {
            // The same trades the holding's return counts: an open window skips CLOSED lots, as on the page.
            $counted   = array_filter($w['trades'], fn ($t) => !($w['is_open'] && $t->status === 'CLOSED'));
            $windows[] = [
                'from'       => $w['start_date']->format('Y-m-d'),
                'to'         => $w['is_open'] ? null : $w['end_date']->format('Y-m-d'),
                'open'       => (bool) $w['is_open'],
                'days'       => (int) $w['duration_days'],
                'invested'   => (float) $w['invested_eur'],
                'return_pct' => $w['percentage_gain'],
                'trades'     => array_values(array_map(fn ($t) => [
                    'date'     => $t->timestamp->format('Y-m-d'),
                    'action'   => (string) $t->action,
                    'quantity' => (float) $t->quantity,
                    'price'    => (float) $t->unit_price,
                ], $counted)),
            ];
        }

        return [
            'trades'       => $trades,
            'windows'      => $windows,
            'lifetime_pct' => $perf['annualized_percentage_gain'] ?? ($perf['percentage_gain'] ?? null),
            'benchmark'    => empty($windows)
                ? [] : $this->_benchmark($trades[0]['iso'] ?? null, $windows[0]['from']),
        ];
    }

    /**
     * VUSA.AS closes from $from, converted into the stock's trade currency at each day's EUR rate, so the
     * mirrored trades are measured in the same currency as your own return (which the app computes at one
     * constant rate, i.e. in the trade currency). Pence (GBp / GBX) are a hundredth of GBP.
     *
     * @return array<string, float> date => close in the trade currency, ascending
     */
    private function _benchmark(?string $tradeIso, string $from): array
    {
        $vusa = StoredCloses::load(TierCalculationService::BENCHMARK_SYMBOL, $from)['prices'];
        if ($tradeIso === null || $tradeIso === 'EUR') {
            return $vusa;
        }
        $pence = in_array($tradeIso, ['GBp', 'GBX'], true);
        $fx    = StoredCloses::load('EUR' . ($pence ? 'GBP' : $tradeIso) . '=X', $from)['prices'];
        if (empty($fx)) {
            return [];
        }
        $fxDates = array_keys($fx);
        $out     = [];
        foreach ($vusa as $date => $price) {
            $rate        = StoredCloses::onOrBefore($fx, $fxDates, $date) ?? $fx[$fxDates[0]];
            $out[$date] = $price * $rate * ($pence ? 100.0 : 1.0);
        }

        return $out;
    }

    /**
     * Your trades in the symbol, oldest first: date, action, quantity, unit price (split-adjusted, as trades
     * are rewritten on a split), and the trade currency's ISO code and display symbol.
     *
     * @return array<int, array{date: string, action: string, quantity: float, price: float, iso: ?string,
     *                          code: string}>
     */
    private function _trades(int $userId, string $symbol): array
    {
        return Trade::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->where('symbol', $symbol)
            ->with('tradeCurrencyModel')
            ->orderBy('timestamp')
            ->orderBy('id')
            ->get()
            ->map(fn ($t) => [
                'date'     => $t->timestamp->format('Y-m-d'),
                'action'   => (string) $t->action,
                'quantity' => (float) $t->quantity,
                'price'    => (float) $t->unit_price,
                'iso'      => $t->tradeCurrencyModel?->iso_code,
                'code'     => html_entity_decode(
                    (string) ($t->tradeCurrencyModel?->display_code ?? ''), ENT_QUOTES | ENT_HTML5
                ),
            ])
            ->all();
    }

    /**
     * Daily closes from a year (plus margin) before the first history day, so every day's 1Y market return
     * can be traced to its two closes. Where the stored history starts later, the missing span comes from
     * HistoricalPriceCache, as for the tier itself (cached, never written to the database).
     *
     * @return array{prices: array<string, float>, currency: ?string, iso: ?string} date => close, ascending;
     *         currency is the display symbol (e.g. "$"), as the rest of the app writes prices
     */
    private function _quotes(int $userId, string $symbol, string $firstDay): array
    {
        $from   = Carbon::parse($firstDay)->subDays(PerformanceDataset::PRICE_PADDING_DAYS)->format('Y-m-d');
        $closes = StoredCloses::load($symbol, $from);

        return [
            'prices'   => $closes['prices'],
            'currency' => $this->_displayCode($userId, $closes['currency']),
            'iso'      => $closes['currency'],
        ];
    }

    /**
     * The currency's display symbol ("$", "€", "GBX"), decoded from its HTML entity since the chart writes
     * it as text. Falls back to the ISO code.
     */
    private function _displayCode(int $userId, ?string $iso): ?string
    {
        if ($iso === null) {
            return null;
        }
        $code = Currency::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->where('iso_code', $iso)
            ->value('display_code');

        return $code !== null ? html_entity_decode((string) $code, ENT_QUOTES | ENT_HTML5) : $iso;
    }
}
