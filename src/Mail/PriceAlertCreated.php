<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

use ovidiuro\myfinance2\Mail\Concerns\HasAppLabel;

class PriceAlertCreated extends Mailable
{
    use Queueable, SerializesModels, HasAppLabel;

    /** @var \ovidiuro\myfinance2\App\Models\PriceAlert[] */
    private array $_alerts;

    /** 'manual' | 'suggestion' */
    private string $_source;

    /** symbol => string[] account names */
    private array $_accountNames;

    /**
     * @param \ovidiuro\myfinance2\App\Models\PriceAlert[] $alerts
     * @param string $source        'manual' or 'suggestion'
     * @param array  $accountNames  symbol => string[] account names
     */
    public function __construct(array $alerts, string $source = 'manual', array $accountNames = [])
    {
        $this->_alerts       = $alerts;
        $this->_source       = $source;
        $this->_accountNames = $accountNames;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $count = count($this->_alerts);
        $first = $this->_alerts[0];

        $label = $this->_appLabel();

        if ($this->_source === 'suggestion') {
            $subject = "{$label} {$count} price alert suggestion(s) created";
        } else {
            $typeLabel = $first->alert_type === 'PRICE_ABOVE' ? '▲ Above' : '▼ Below';
            $currency  = $first->tradeCurrencyModel?->iso_code ?? '';
            $subject   = "{$label} Alert created — {$first->symbol} {$typeLabel}"
                . ' ' . number_format((float) $first->target_price, 2) . ($currency ? " {$currency}" : '');
        }

        return $this->subject($subject)
            ->view('myfinance2::emails.price-alert-created')
            ->with([
                'alerts'        => $this->_alerts,
                'source'        => $this->_source,
                'accountNames'  => $this->_accountNames,
                'dashboardUrl'  => route('myfinance2::price-alerts.index'),
            ]);
    }
}
