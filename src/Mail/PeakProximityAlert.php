<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Route;

use ovidiuro\myfinance2\App\Services\MoneyFormat;
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

    /**
     * Create a new message instance.
     *
     * @param string $symbol
     * @param array  $quoteData        the dashboard item for this symbol (all three cards' data)
     * @param array  $triggeredWindows window label => exit-zone entry (proximity_pct, peak date)
     */
    public function __construct(
        string $symbol,
        array $quoteData,
        array $triggeredWindows
    )
    {
        $this->_symbol           = $symbol;
        $this->_quoteData        = $quoteData;
        $this->_triggeredWindows = $triggeredWindows;
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

        // All triggered windows, largest first, e.g.
        // "[MyFinance2-LOCAL] Open position near peak => GOOGL -6.65% to 2Y, -1.46% to 3M"
        $parts = [];
        foreach ($orderedWindows as $window) {
            $parts[] = $this->_signedPct($this->_triggeredWindows[$window]['proximity_pct'] ?? null)
                . ' to ' . strtoupper($window);
        }
        $subject = "{$label} Open position near peak => {$this->_symbol} " . implode(', ', $parts);

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
                'watchlistUrl'     => $watchlistUrl,
                'yahooUrl'         => self::YAHOO_QUOTE_URL . $this->_symbol,
                'vusaUrl'          => self::YAHOO_QUOTE_URL . self::BENCHMARK_SYMBOL,
            ]);
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
