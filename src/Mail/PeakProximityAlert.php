<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Route;

use ovidiuro\myfinance2\App\Services\MoneyFormat;
use ovidiuro\myfinance2\App\Services\PeakProximityAlertService;
use ovidiuro\myfinance2\App\Services\QuadrantClassifier;
use ovidiuro\myfinance2\App\Services\TierCalculationService;
use ovidiuro\myfinance2\Mail\Concerns\HasAppLabel;

/**
 * Peak-proximity exit-hint email. This class doubles as the email's presenter: every derived value
 * (per-window targets, the quadrant table, the tier/quadrant/action badge classes, the open-position
 * timeline) is computed here and handed to the view as plain data, so the Blade template only
 * formats and echoes, holding no business logic of its own.
 */
class PeakProximityAlert extends Mailable
{
    use Queueable, SerializesModels, HasAppLabel;

    private const BENCHMARK_SYMBOL = 'VUSA.AS';
    private const YAHOO_QUOTE_URL  = 'https://finance.yahoo.com/quote/';

    /** Display order for windows: largest window first, down to the smallest. */
    private const WINDOW_ORDER = ['2y', '1y', '6m', '3m'];

    /** Window key => human label. */
    private const WINDOW_LABELS = ['3m' => '3M', '6m' => '6M', '1y' => '1Y', '2y' => '2Y'];

    /** Head-action badge classes. */
    private const ACTION_CLASSES = [
        'ACCUMULATE' => 'bg-success',
        'HOLD'       => 'bg-info text-dark',
        'REDUCE'     => 'bg-warning text-dark',
        'EXIT'       => 'bg-danger',
    ];

    private string $_symbol;
    private array $_quoteData;
    private array $_triggeredWindows;
    private bool $_isReminder;
    private array $_windowThresholds;

    /**
     * Create a new message instance.
     *
     * @param string $symbol
     * @param array  $quoteData        the dashboard item for this symbol (all three cards' data)
     * @param array  $triggeredWindows window label => exit-zone entry (proximity_pct, peak date)
     * @param bool   $isReminder       true for a cadence reminder (vs the first email of the episode)
     * @param array  $windowThresholds window label => proximity threshold (%) at which it fires
     */
    public function __construct(
        string $symbol,
        array $quoteData,
        array $triggeredWindows,
        bool $isReminder = false,
        array $windowThresholds = []
    )
    {
        $this->_symbol           = $symbol;
        $this->_quoteData        = $quoteData;
        $this->_triggeredWindows = $triggeredWindows;
        $this->_isReminder       = $isReminder;
        $this->_windowThresholds = $windowThresholds;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $label          = $this->_appLabel();
        $orderedWindows = $this->_orderedWindows();

        $cat       = $this->_quoteData['categorization'] ?? [];
        $tier      = $cat['effective_tier'] ?? null;
        $tierLabel = TierCalculationService::tierLabel($tier);
        $action    = $cat['action'] ?? null;

        // Lead the subject with the tier (and action) so a near-peak winner reads differently from a
        // near-peak laggard at a glance, e.g.
        // "[MyFinance2-LOCAL] EZJ.L (Bronze, EXIT) near peak => -4.25% to 6M, +0.00% to 3M"
        $parts = [];
        foreach ($orderedWindows as $window) {
            $parts[] = $this->_signedPct($this->_triggeredWindows[$window]['proximity_pct'] ?? null)
                . ' to ' . strtoupper($window);
        }

        $tierTag = $this->_tierTag($tierLabel, $action);
        $prefix  = $this->_isReminder ? 'Reminder: ' : '';
        $subject = "{$label} {$prefix}{$this->_symbol}{$tierTag} near peak => " . implode(', ', $parts);

        $watchlistUrl = Route::has('myfinance2::watchlist-symbols.index')
            ? route('myfinance2::watchlist-symbols.index')
            : self::YAHOO_QUOTE_URL . $this->_symbol;

        return $this->subject($subject)
            ->view('myfinance2::emails.peak-proximity-alert')
            ->with([
                'symbol'         => $this->_symbol,
                'quoteData'      => $this->_quoteData,
                'summaryWindows' => $this->_summaryWindows(),
                'nearCount'      => count($this->_triggeredWindows),
                'triggerSummary' => $this->_triggerSummaryParts($orderedWindows),
                'todayGainEur'   => $this->_quoteData['today_gain_eur'] ?? null,
                'todayGainPct'   => $this->_quoteData['today_gain_pct'] ?? null,
                'sellGainEur'    => $this->_quoteData['unrealized_gain_eur'] ?? null,
                'sellGainPct'    => $this->_quoteData['unrealized_gain_pct'] ?? null,
                'openWindow'     => $this->_openWindow(),
                'quadrant'       => $this->_quadrant(),
                'openPositions'  => $this->_openPositions(),
                'tier'           => $tier,
                'tierLabel'      => $tierLabel,
                'tierClass'      => $tier ? TierCalculationService::tierBadgeClass($tier) : '',
                'headAction'     => $action,
                'isReminder'     => $this->_isReminder,
                'watchlistUrl'   => $watchlistUrl,
                'yahooUrl'       => self::YAHOO_QUOTE_URL . $this->_symbol,
                'vusaUrl'        => self::YAHOO_QUOTE_URL . self::BENCHMARK_SYMBOL,
            ]);
    }

    /**
     * Every window (largest first), triggered or not, with its peak, its trigger target (the price at
     * which it would go near peak: the window threshold % below the peak) and how far the price still
     * has to run to reach it. Windows already near peak are flagged so the row is highlighted.
     *
     * @return array
     */
    private function _summaryWindows(): array
    {
        $price     = isset($this->_quoteData['price']) ? (float) $this->_quoteData['price'] : null;
        $exitZones = $this->_quoteData['categorization']['exit_zones'] ?? [];

        return PeakProximityAlertService::buildSummaryWindows(
            $price,
            $exitZones,
            array_keys($this->_triggeredWindows),
            $this->_windowThresholds
        );
    }

    /**
     * The header sub-line parts, largest window first: "6M -3.47% from peak", one per triggered window.
     *
     * @param array $orderedWindows
     *
     * @return array
     */
    private function _triggerSummaryParts(array $orderedWindows): array
    {
        $parts = [];
        foreach ($orderedWindows as $window) {
            $prox  = $this->_triggeredWindows[$window]['proximity_pct'] ?? null;
            $label = self::WINDOW_LABELS[$window] ?? strtoupper($window);
            $parts[] = $prox !== null
                ? $label . ' ' . $this->_signedPct((float) $prox) . ' from peak'
                : $label;
        }

        return $parts;
    }

    /**
     * The open performance window (the position still held today), or null when there is none.
     *
     * @return array|null
     */
    private function _openWindow(): ?array
    {
        $perf = $this->_quoteData['performance'] ?? [];
        if (empty($perf['has_data'])) {
            return null;
        }

        foreach ($perf['windows'] ?? [] as $window) {
            if (!empty($window['is_open'])) {
                return $window;
            }
        }

        return null;
    }

    /**
     * The Quadrant card view model (header figures + per-window rows), or null when the symbol has no
     * categorization. Every tier / quadrant / action badge class and label is resolved here so the
     * view only echoes them.
     *
     * @return array|null
     */
    private function _quadrant(): ?array
    {
        $cat = $this->_quoteData['categorization'] ?? [];
        if (empty($cat)) {
            return null;
        }

        $tableMeta = $this->_quoteData['table_meta'] ?? [];
        $tier      = $cat['effective_tier'] ?? null;
        $action    = $cat['action'] ?? null;

        return [
            'tier_label'   => $tier ? TierCalculationService::tierLabel($tier) : 'Unrated',
            'tier_class'   => $tier ? TierCalculationService::tierBadgeClass($tier) : 'bg-light text-dark',
            'gain_eur'     => $tableMeta['basis_gain_eur'] ?? null,
            'basis_val'    => $cat['basis_value'] ?? null,
            'action'       => $action,
            'action_class' => $this->_actionClass($action),
            'xirr_pct'     => $cat['xirr_pct'] ?? null,
            'alpha_pct'    => $cat['alpha_vs_vusa_pct'] ?? null,
            'rows'         => $this->_quadrantRows(),
        ];
    }

    /**
     * The per-window rows of the Quadrant table: tier (derived from each window's gain), quadrant,
     * action, risk, from-peak proximity and P&L at peak, each with its resolved badge class/label.
     *
     * @return array
     */
    private function _quadrantRows(): array
    {
        $cat        = $this->_quoteData['categorization'] ?? [];
        $periods    = $cat['periods'] ?? [];
        $exitZones  = $cat['exit_zones'] ?? [];
        $peakPnlMap = ($this->_quoteData['table_meta'] ?? [])['period_peak_pnl'] ?? [];
        $tierCalc   = new TierCalculationService();

        $rows = [];
        foreach (self::WINDOW_LABELS as $key => $label) {
            $pd     = $periods[$key] ?? null;
            $gain   = isset($pd['gain']) ? (float) $pd['gain'] : null;
            $quad   = $pd['quadrant'] ?? null;
            $action = $pd['action'] ?? null;
            $ez     = $exitZones[$key] ?? null;
            $pnl    = $peakPnlMap[$key] ?? null;
            $tier   = $gain !== null ? $tierCalc->getTier($gain) : null;

            $rows[] = [
                'label'          => $label,
                'near'           => array_key_exists($key, $this->_triggeredWindows),
                'tier_label'     => $tier !== null ? TierCalculationService::tierLabel($tier) : null,
                'tier_class'     => $tier !== null ? TierCalculationService::tierBadgeClass($tier) : null,
                'quadrant_label' => $quad !== null ? QuadrantClassifier::label($quad) : null,
                'quadrant_class' => $quad !== null ? $this->_quadrantClass($quad) : null,
                'gain'           => $gain,
                'risk'           => $pd['risk'] ?? null,
                'action'         => $action,
                'action_class'   => $this->_actionClass($action),
                'prox'           => isset($ez['proximity_pct']) ? (float) $ez['proximity_pct'] : null,
                'peak_date'      => $ez['peak_price_date'] ?? null,
                'pnl_eur'        => $pnl['eur'] ?? null,
                'pnl_pct'        => $pnl['pct'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * The open positions, each enriched with a merged, newest-first trade + stock-split timeline for
     * the "Open positions" card.
     *
     * @return array
     */
    private function _openPositions(): array
    {
        $positions = $this->_quoteData['open_positions'] ?? [];
        $splits    = $this->_quoteData['stock_splits'] ?? [];

        foreach ($positions as &$position) {
            $position['timeline'] = $this->_positionTimeline($position, $splits);
        }
        unset($position);

        return $positions;
    }

    /**
     * Merge a position's trades and the symbol's stock splits into one newest-first timeline.
     *
     * @param array $position
     * @param array $splits
     *
     * @return array
     */
    private function _positionTimeline(array $position, array $splits): array
    {
        $timeline = [];
        foreach ($position['trades'] ?? [] as $trade) {
            $timeline[] = ['type' => 'trade', 'ts' => $trade->timestamp, 'data' => $trade];
        }
        foreach ($splits as $split) {
            $timeline[] = ['type' => 'split', 'ts' => $split->split_date, 'data' => $split];
        }
        usort($timeline, fn ($a, $b) => $b['ts'] <=> $a['ts']);

        return $timeline;
    }

    /**
     * The badge CSS class for a head action, defaulting to a neutral badge for an unknown value.
     *
     * @param string|null $action
     *
     * @return string
     */
    private function _actionClass(?string $action): string
    {
        return self::ACTION_CLASSES[$action] ?? 'bg-secondary';
    }

    /**
     * The badge CSS class for a quadrant, defaulting to a neutral badge for an unknown value.
     *
     * @param string|null $quadrant
     *
     * @return string
     */
    private function _quadrantClass(?string $quadrant): string
    {
        $map = [
            QuadrantClassifier::STEADY_GROWERS   => 'bg-success',
            QuadrantClassifier::VOLATILE_WINNERS => 'bg-warning text-dark',
            QuadrantClassifier::DEAD_WEIGHT      => 'bg-secondary',
            QuadrantClassifier::DANGER_ZONE      => 'bg-danger',
        ];

        return $map[$quadrant] ?? 'bg-secondary';
    }

    /**
     * The " (Tier, ACTION)" subject fragment, omitting whichever pieces are unknown.
     *
     * @param string|null $tierLabel
     * @param string|null $action
     *
     * @return string
     */
    private function _tierTag(?string $tierLabel, ?string $action): string
    {
        $bits = array_filter([$tierLabel, $action]);
        if (empty($bits)) {
            return '';
        }

        return ' (' . implode(', ', $bits) . ')';
    }

    /**
     * Triggered window keys ordered from the largest window (2Y) down to the smallest (3M).
     * Any unexpected window key falls to the end, preserving its relative order.
     *
     * @return array
     */
    private function _orderedWindows(): array
    {
        $ordered = [];
        foreach (self::WINDOW_ORDER as $window) {
            if (array_key_exists($window, $this->_triggeredWindows)) {
                $ordered[] = $window;
            }
        }
        foreach (array_keys($this->_triggeredWindows) as $window) {
            if (!in_array($window, $ordered, true)) {
                $ordered[] = (string) $window;
            }
        }

        return $ordered;
    }

    /**
     * Format a percentage with an explicit sign, e.g. "-3.49%" or "+1.20%".
     *
     * @param float|null $value
     *
     * @return string
     */
    private function _signedPct(?float $value): string
    {
        if ($value === null) {
            return 'n/a';
        }

        $sign = $value > 0 ? '+' : ''; // negatives already carry their minus
        return $sign . MoneyFormat::get_formatted_pct($value) . '%';
    }
}
