<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

use ovidiuro\myfinance2\App\Models\PriceAlert;
use ovidiuro\myfinance2\App\Services\MoneyFormat;
use ovidiuro\myfinance2\App\Services\TierCalculationService;
use ovidiuro\myfinance2\App\Services\TierSignalChip;
use ovidiuro\myfinance2\Mail\Concerns\DescribesPeakProximityReading;
use ovidiuro\myfinance2\Mail\Concerns\HasAppLabel;
use ovidiuro\myfinance2\Mail\Concerns\LinksSymbolActions;

/**
 * Price alert email (and its split-anomaly warning), laid out as the peak-proximity alert: the subject and header
 * lead with the symbol and its tier reading ("ABN.AS (Gold) above target 39.00 € => now 39.12 €"),
 * then a Summary with today's move and the unrealized gain, the position and the same row of buttons.
 */
class PriceAlertTriggered extends Mailable
{
    use Queueable, SerializesModels, HasAppLabel, DescribesPeakProximityReading, LinksSymbolActions;

    private PriceAlert $_alert;
    private float $_currentPrice;
    private ?array $_position;
    private bool $_isSplitWarning;
    private ?array $_tierReading;

    /**
     * Create a new message instance.
     *
     * @param PriceAlert $alert
     * @param float      $currentPrice
     * @param array|null $position       AlertService::emailPosition(): today's move and, when held, the quantity,
     *                                   the average cost and the unrealized gain if sold now (fees included, EUR)
     * @param bool       $isSplitWarning
     * @param array|null $tierReading    StoredTierReading::forSymbol(), null when the symbol has no stored tier
     */
    public function __construct(
        PriceAlert $alert,
        float $currentPrice,
        ?array $position = null,
        bool $isSplitWarning = false,
        ?array $tierReading = null
    )
    {
        $this->_alert          = $alert;
        $this->_currentPrice   = $currentPrice;
        $this->_position       = $position;
        $this->_isSplitWarning = $isSplitWarning;
        $this->_tierReading    = $tierReading;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $alert         = $this->_alert;
        $currencyHtml  = html_entity_decode($alert->tradeCurrencyModel?->display_code ?? '', ENT_HTML5, 'UTF-8');
        $currencyLabel = html_entity_decode(strip_tags($currencyHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $direction     = $this->_direction();
        $tier          = $this->_tierReading['effective_tier'] ?? null;
        $tierLabel     = TierCalculationService::tierLabel($tier);
        $tierSummary   = $this->_tierReading !== null ? $this->_tierSummary(
            $tierLabel,
            self::readingAction($this->_tierReading),
            $this->_tierReading['range_bound'] ?? $this->_tierReading['take_profit']
                ?? TierSignalChip::shown($this->_tierReading)
        ) : '';

        $target  = trim(MoneyFormat::get_formatted_price_display($currencyLabel, (float) $alert->target_price, true));
        $current = trim(MoneyFormat::get_formatted_price_display($currencyLabel, $this->_currentPrice, true));
        $tierTag = $tierSummary !== '' ? " ({$tierSummary})" : '';
        $subject = $this->_isSplitWarning
            ? "{$this->_appLabel()} {$alert->symbol}{$tierTag} price alert may be stale => possible split"
            : "{$this->_appLabel()} {$alert->symbol}{$tierTag} {$direction} target {$target} => now {$current}";

        return $this->subject($subject)
            ->view('myfinance2::emails.price-alert-triggered')
            ->with([
                'alert'          => $alert,
                'currentPrice'   => $this->_currentPrice,
                'direction'      => $direction,
                'headline'       => $this->_isSplitWarning
                    ? 'Price alert may be stale (possible split)'
                    : "Price alert: {$direction} target",
                'tierLabel'      => $tierLabel,
                'tierClass'      => $tier !== null ? TierCalculationService::tierBadgeClass($tier) : '',
                'tierSummary'    => $tierSummary,
                // The same "Today's move" and unrealized gain lines as the peak-proximity alerts.
                'todayGainEur'   => $this->_position['today_gain_eur'] ?? null,
                'todayGainPct'   => $this->_position['today_gain_pct'] ?? null,
                'sellGainEur'    => $this->_position['gain_eur'] ?? null,
                'sellGainPct'    => $this->_position['gain_pct'] ?? null,
                'quantity'       => $this->_position['quantity'] ?? null,
                'avgUnitCost'    => $this->_position['avg_unit_cost'] ?? null,
                'currencyHtml'   => $currencyHtml,
                'currencyLabel'  => $currencyLabel,
                'links'          => $this->_links(),
                'isSplitWarning' => $this->_isSplitWarning,
            ]);
    }

    /**
     * Where the price sits against the target: "above" / "below", or "at" when it equals it.
     */
    private function _direction(): string
    {
        $target = (float) $this->_alert->target_price;
        if (abs($this->_currentPrice - $target) < 1e-9) {
            return 'at';
        }

        return $this->_currentPrice > $target ? 'above' : 'below';
    }

    /**
     * The buttons at the end of the email: the order (not for a split warning), managing and pausing the alert and
     * the watchlist, the same row as the peak-proximity alert.
     *
     * @return array
     */
    private function _links(): array
    {
        $alert = $this->_alert;
        $side  = $alert->alert_type === 'PRICE_ABOVE' ? 'SELL' : 'BUY';
        $links = [];
        if (!$this->_isSplitWarning) {
            $links[] = [
                'label' => self::_orderLabel($side),
                'url'   => $this->_orderUrl($alert->symbol, $side, $this->_currentPrice, [
                    'source' => 'alert', 'alert_id' => $alert->id,
                ]),
            ];
        }
        $links[] = [
            'label'   => '✏️ Manage Alert',
            'url'     => route('myfinance2::price-alerts.edit', $alert->id),
            'variant' => 'secondary',
        ];
        $links[] = [
            'label'   => '⏸ Pause Alert',
            'url'     => route('myfinance2::price-alerts.pause', $alert->id),
            'variant' => 'warning',
        ];
        $links[] = [
            'label'   => '→ Open Watchlist Symbols',
            'url'     => $this->_watchlistUrl($alert->symbol),
            'variant' => 'secondary',
        ];

        return $links;
    }
}
