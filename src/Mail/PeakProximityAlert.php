<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Route;

use ovidiuro\myfinance2\App\Services\MoneyFormat;
use ovidiuro\myfinance2\App\Services\TierCalculationService;
use ovidiuro\myfinance2\Mail\Concerns\HasAppLabel;

class PeakProximityAlert extends Mailable
{
    use Queueable, SerializesModels, HasAppLabel;

    private const BENCHMARK_SYMBOL = 'VUSA.AS';
    private const YAHOO_QUOTE_URL  = 'https://finance.yahoo.com/quote/';

    /** Display order for windows: largest window first, down to the smallest. */
    private const WINDOW_ORDER = ['2y', '1y', '6m', '3m'];

    private string $_symbol;
    private array $_quoteData;
    private array $_triggeredWindows;
    private bool $_isReminder;

    /**
     * Create a new message instance.
     *
     * @param string $symbol
     * @param array  $quoteData        the dashboard item for this symbol (all three cards' data)
     * @param array  $triggeredWindows window label => exit-zone entry (proximity_pct, peak date)
     * @param bool   $isReminder       true for a cadence reminder (vs the first email of the episode)
     */
    public function __construct(
        string $symbol,
        array $quoteData,
        array $triggeredWindows,
        bool $isReminder = false
    )
    {
        $this->_symbol           = $symbol;
        $this->_quoteData        = $quoteData;
        $this->_triggeredWindows = $triggeredWindows;
        $this->_isReminder       = $isReminder;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $label            = $this->_appLabel();
        $closestWindow    = $this->_closestWindow();
        $closestProximity = $this->_triggeredWindows[$closestWindow]['proximity_pct'] ?? null;
        $orderedWindows   = $this->_orderedWindows();

        $tier      = $this->_quoteData['categorization']['effective_tier'] ?? null;
        $tierLabel = TierCalculationService::tierLabel($tier);
        $action    = $this->_quoteData['categorization']['action'] ?? null;

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
                'symbol'           => $this->_symbol,
                'quoteData'        => $this->_quoteData,
                'triggeredWindows' => $this->_triggeredWindows,
                'orderedWindows'   => $orderedWindows,
                'closestWindow'    => $closestWindow,
                'closestProximity' => $closestProximity,
                'tier'             => $tier,
                'tierLabel'        => $tierLabel,
                'headAction'       => $action,
                'isReminder'       => $this->_isReminder,
                'watchlistUrl'     => $watchlistUrl,
                'yahooUrl'         => self::YAHOO_QUOTE_URL . $this->_symbol,
                'vusaUrl'          => self::YAHOO_QUOTE_URL . self::BENCHMARK_SYMBOL,
            ]);
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
     * The window with the largest (least negative) proximity_pct among the triggered windows,
     * i.e. the one nearest its peak. Falls back to the first triggered key.
     *
     * @return string
     */
    private function _closestWindow(): string
    {
        $best       = null;
        $bestWindow = (string) array_key_first($this->_triggeredWindows);

        foreach ($this->_triggeredWindows as $window => $entry) {
            $proximity = $entry['proximity_pct'] ?? null;
            if ($proximity === null) {
                continue;
            }
            if ($best === null || $proximity > $best) {
                $best       = $proximity;
                $bestWindow = (string) $window;
            }
        }

        return $bestWindow;
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
