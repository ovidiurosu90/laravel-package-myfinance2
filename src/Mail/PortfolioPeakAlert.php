<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Route;

use ovidiuro\myfinance2\Mail\Concerns\HasAppLabel;

/**
 * The opt-in Portfolio Peak Alert email. Fires when the portfolio's EUR gain or return on cost has
 * rallied to within N% of its rolling 6M/1Y/2Y high, one per user per day with a flat reminder
 * interval while the condition holds. The VUSA.AS benchmark distance is context only, never a gate.
 */
class PortfolioPeakAlert extends Mailable
{
    use Queueable, SerializesModels, HasAppLabel;

    private const BENCHMARK_SYMBOL = 'VUSA.AS';
    private const YAHOO_QUOTE_URL  = 'https://finance.yahoo.com/quote/';

    /**
     * @param array      $pairs            triggered (metric, window) pairs
     * @param float|null $changePctCurrent current return on cost (%)
     * @param float|null $changeEurCurrent current EUR gain
     * @param float|null $vusaChangePct    VUSA.AS distance from its 2Y high (context only, nullable)
     * @param array      $breakdown        full per-window diagnostic rows (all windows, 3M context)
     */
    public function __construct(
        public readonly array  $pairs,
        public readonly ?float $changePctCurrent,
        public readonly ?float $changeEurCurrent,
        public readonly ?float $vusaChangePct,
        public readonly array  $breakdown = [],
    ) {}

    /**
     * @return $this
     */
    public function build()
    {
        $label = $this->_appLabel();

        // Subject leads with the longest triggered window and the closest proximity, matching the
        // existing alert subject style. No dashes in generated text.
        $order   = ['2y' => 3, '1y' => 2, '6m' => 1];
        $windows = array_unique(array_column($this->pairs, 'window'));
        usort($windows, fn ($a, $b) => ($order[$b] ?? 0) <=> ($order[$a] ?? 0));
        $longestWindow = strtoupper($windows[0] ?? '');
        $closest       = max(array_column($this->pairs, 'proximity_pct'));

        $settingsUrl = Route::has('myfinance2::portfolio-peak-alerts.index')
            ? route('myfinance2::portfolio-peak-alerts.index')
            : url('/portfolio-peak-alerts');

        return $this->subject(
                "{$label} Portfolio near {$longestWindow} high => {$closest}% from peak"
            )
            ->view('myfinance2::emails.portfolio-peak-alert')
            ->with([
                'pairs'            => $this->pairs,
                'breakdown'        => $this->breakdown,
                'changePctCurrent' => $this->changePctCurrent,
                'changeEurCurrent' => $this->changeEurCurrent,
                'vusaChangePct'    => $this->vusaChangePct,
                'settingsUrl'      => $settingsUrl,
                'vusaUrl'          => self::YAHOO_QUOTE_URL . self::BENCHMARK_SYMBOL,
            ]);
    }
}
