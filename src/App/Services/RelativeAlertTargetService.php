<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

use ovidiuro\myfinance2\App\Models\PriceAlert;

/**
 * Web-side companion of AlertReferenceResolver for RELATIVE price alerts: resolves the target on
 * save (backfilling a window that has too little stored history), on resume, and builds the
 * form preview. The engine uses the resolver directly, since it must stay DB only.
 *
 * Resolved from the container, so tests can swap livePrice() / backfill() for stubs.
 */
class RelativeAlertTargetService
{
    private AlertReferenceResolver $_resolver;

    public function __construct(?AlertReferenceResolver $resolver = null)
    {
        $this->_resolver = $resolver ?? new AlertReferenceResolver();
    }

    /**
     * Resolve a RELATIVE alert before it is saved and copy the target / reference onto it. When
     * the window is not covered by stored history, backfill it once and try again; when it still
     * is not covered, fail validation with how much history there is.
     *
     * @throws ValidationException
     */
    public function resolveForSave(PriceAlert $alert): void
    {
        $symbol    = (string) $alert->symbol;
        $window    = (string) $alert->reference_window;
        $livePrice = $this->livePrice($symbol);

        if ($this->_resolver->target($alert, $livePrice) === null) {
            $start = Carbon::today()->subDays(PriceAlert::REFERENCE_WINDOW_DAYS[$window] ?? 730)
                ->format('Y-m-d');
            $this->backfill($symbol, $start, Carbon::today()->format('Y-m-d'));
            $this->_resolver->forget($symbol);
        }

        if (!$this->_resolver->refresh($alert, $livePrice, false)) {
            $days = $this->_resolver->historyDays($symbol);
            throw ValidationException::withMessages([
                'reference_key' => "Only {$days} days of price history for {$symbol}; choose a shorter window.",
            ]);
        }
    }

    /**
     * Resolve the targets of alerts that were just resumed, so a resumed alert never shows a
     * target that is days old. DB only (no live bump); the engine refines it on its next tick.
     *
     * @param iterable<PriceAlert> $alerts
     */
    public function refreshResumed(iterable $alerts): void
    {
        foreach ($alerts as $alert) {
            if ($alert->isRelative() && !$this->_resolver->refresh($alert, null)) {
                Log::info("RelativeAlertTargetService: alert #{$alert->id} ({$alert->symbol}) resumed"
                    . ' without a resolvable reference');
            }
        }
    }

    /**
     * Preview data for the alert form: the live price and the eight references (high / low per
     * window) with their dates and coverage. A window without stored coverage falls back to the
     * cached Yahoo history for display only, flagged so the form can say it will be backfilled
     * on save.
     */
    public function previewData(string $symbol): array
    {
        $livePrice  = $this->livePrice($symbol);
        $references = $this->_resolver->describe($symbol, $livePrice);

        $uncovered = array_filter($references, fn (array $ref) => !$ref['covered']);
        if (!empty($uncovered)) {
            $fallback = AlertReferenceResolver::describeCloses(
                $this->fallbackCloses($symbol),
                $livePrice,
                Carbon::today()->format('Y-m-d')
            );
            foreach (array_keys($uncovered) as $key) {
                if (!empty($fallback[$key]['covered'])) {
                    $references[$key] = $fallback[$key] + ['backfill' => true];
                }
            }
        }

        foreach ($references as $key => $ref) {
            // Direction of the offset: below a HIGH, above a LOW. The form only multiplies.
            $references[$key]['direction'] = $ref['type'] === 'HIGH' ? -1 : 1;
            $references[$key]['backfill']  = !empty($ref['backfill']);
        }

        return [
            'symbol'           => $symbol,
            'live_price'       => $livePrice,
            'live_price_label' => $livePrice !== null ? MoneyFormat::get_formatted_price($livePrice, true) : null,
            'max_offset'       => PriceAlert::MAX_OFFSET_PCT,
            'references'       => $references,
        ];
    }

    /**
     * Live price of the symbol from the 2-minute quote cache (pre / post-market included, like
     * the engine), or null when no quote is available.
     */
    public function livePrice(string $symbol): ?float
    {
        $quotes = (new FinanceUtils())->getQuotes([$symbol], null, false);
        $price  = is_array($quotes) ? ($quotes[$symbol]['price'] ?? null) : null;

        return is_numeric($price) && (float) $price > 0.0 ? (float) $price : null;
    }

    /**
     * Persist the stored daily closes of a symbol between two dates (same path as the chart
     * modal's "Populate historical data" button).
     */
    public function backfill(string $symbol, string $start, string $end): void
    {
        try {
            $count = HistoricalBackfill::persistSymbol(new FinanceAPI(), $symbol, $start, $end);
            Log::info("RelativeAlertTargetService: backfilled {$symbol} from {$start} => "
                . ($count ?? 0) . ' closes');
        } catch (\Throwable $e) {
            Log::warning("RelativeAlertTargetService: backfill failed for {$symbol}: " . $e->getMessage());
        }
    }

    /**
     * Closes from the cached Yahoo history over the longest window, for the preview only.
     *
     * @return array<string, float> date => close
     */
    public function fallbackCloses(string $symbol): array
    {
        $from = Carbon::today()->subDays(max(PriceAlert::REFERENCE_WINDOW_DAYS))->format('Y-m-d');

        try {
            $fetched = (new HistoricalPriceCache())->fetch($symbol, $from, Carbon::today()->format('Y-m-d'));
        } catch (\Throwable $e) {
            Log::warning("RelativeAlertTargetService: fallback history failed for {$symbol}: "
                . $e->getMessage());
            return [];
        }

        return $fetched['prices'] ?? [];
    }
}
