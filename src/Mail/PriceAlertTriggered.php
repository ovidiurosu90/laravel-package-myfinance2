<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

use ovidiuro\myfinance2\App\Models\PriceAlert;
use ovidiuro\myfinance2\App\Services\MoneyFormat;
use ovidiuro\myfinance2\Mail\Concerns\HasAppLabel;

class PriceAlertTriggered extends Mailable
{
    use Queueable, SerializesModels, HasAppLabel;

    private PriceAlert $_alert;
    private float $_currentPrice;
    private ?array $_projectedGain;
    private bool $_isSplitWarning;

    /**
     * Create a new message instance.
     *
     * @param PriceAlert $alert
     * @param float      $currentPrice
     * @param array|null $projectedGain
     * @param bool       $isSplitWarning
     */
    public function __construct(
        PriceAlert $alert,
        float $currentPrice,
        ?array $projectedGain = null,
        bool $isSplitWarning = false
    )
    {
        $this->_alert         = $alert;
        $this->_currentPrice  = $currentPrice;
        $this->_projectedGain = $projectedGain;
        $this->_isSplitWarning = $isSplitWarning;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $alert = $this->_alert;
        $currencyDisplayCode = $alert->tradeCurrencyModel?->display_code ?? '';
        $currencyIsoCode = $alert->tradeCurrencyModel?->iso_code ?? '';

        $createOrderAction = $alert->alert_type === 'PRICE_ABOVE' ? 'SELL' : 'BUY';
        $createOrderUrl = route('myfinance2::orders.create') . '?' . http_build_query([
            'symbol'      => $alert->symbol,
            'action'      => $createOrderAction,
            'limit_price' => number_format($this->_currentPrice, 4, '.', ''),
            'source'      => 'alert',
            'alert_id'    => $alert->id,
        ]);

        $manageAlertUrl = route('myfinance2::price-alerts.edit', $alert->id);
        $pauseAlertUrl  = route('myfinance2::price-alerts.pause', $alert->id);

        $label          = $this->_appLabel();
        $formattedPrice = MoneyFormat::get_formatted_price((float) $alert->target_price);
        $currencyLabel  = html_entity_decode(strip_tags($currencyDisplayCode), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $subject = $this->_isSplitWarning
            ? "{$label} ⚠️ Alert for {$alert->symbol} may be stale (possible split)"
            : "{$label} {$alert->symbol} reached {$formattedPrice} {$currencyLabel}";

        return $this->subject($subject)
            ->view('myfinance2::emails.price-alert-triggered')
            ->with([
                'alert'              => $alert,
                'currentPrice'       => $this->_currentPrice,
                'projectedGain'      => $this->_projectedGain,
                'currencyDisplayCode' => $currencyDisplayCode,
                'currencyIsoCode'    => $currencyIsoCode,
                'createOrderUrl'     => $createOrderUrl,
                'manageAlertUrl'     => $manageAlertUrl,
                'pauseAlertUrl'      => $pauseAlertUrl,
                'isSplitWarning'     => $this->_isSplitWarning,
            ]);
    }
}
