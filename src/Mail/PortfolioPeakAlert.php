<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Route;

use ovidiuro\myfinance2\App\Services\PortfolioPeakAlertService;
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

    // Up to six pairs can fire at once (three trigger windows x two metrics); listing them all
    // would push the subject past what any mail client shows, so the tail is summarised.
    private const SUBJECT_MAX_NAMES = 4;

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

        // Subject states how many windows fired and names them, in the same order (and with the
        // same labels) as the email's table. An earlier version paired the longest triggered window
        // with the closest proximity, which described a row that did not exist whenever those came
        // from different pairs. No dashes in generated text.
        $names  = array_map(
            fn (array $pair) => PortfolioPeakAlertService::pairLabel($pair),
            $this->pairs
        );
        $count  = count($names);
        $shown  = array_slice($names, 0, self::SUBJECT_MAX_NAMES);
        $hidden = $count - count($shown);
        $list   = implode(', ', $shown) . ($hidden > 0 ? ", +{$hidden} more" : '');
        $noun   = $count === 1 ? 'window' : 'windows';

        $settingsUrl = Route::has('myfinance2::portfolio-peak-alerts.index')
            ? route('myfinance2::portfolio-peak-alerts.index')
            : url('/portfolio-peak-alerts');

        return $this->subject(
                "{$label} Portfolio near highs => {$count} {$noun} ({$list})"
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
