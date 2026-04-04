<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

use ovidiuro\myfinance2\Mail\Concerns\HasAppLabel;

class PriceAlertStateChanged extends Mailable
{
    use Queueable, SerializesModels, HasAppLabel;

    /** @var \ovidiuro\myfinance2\App\Models\PriceAlert[] */
    private array $_alerts;

    /** 'paused' | 'resumed' */
    private string $_action;

    /** symbol => string[] account names */
    private array $_accountNames;

    /**
     * @param \ovidiuro\myfinance2\App\Models\PriceAlert[] $alerts
     * @param string $action       'paused' or 'resumed'
     * @param array  $accountNames symbol => string[] account names
     */
    public function __construct(array $alerts, string $action, array $accountNames = [])
    {
        $this->_alerts       = $alerts;
        $this->_action       = $action;
        $this->_accountNames = $accountNames;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $count  = count($this->_alerts);
        $action = ucfirst($this->_action);
        $label  = $this->_appLabel();

        if ($count === 1) {
            $first   = $this->_alerts[0];
            $subject = "{$label} Alert #{$first->id} {$this->_action} — {$first->symbol}";
        } else {
            $subject = "{$label} {$count} alerts {$this->_action}";
        }

        return $this->subject($subject)
            ->view('myfinance2::emails.price-alert-state-changed')
            ->with([
                'alerts'       => $this->_alerts,
                'action'       => $action,
                'actionRaw'    => $this->_action,
                'accountNames' => $this->_accountNames,
                'dashboardUrl' => route('myfinance2::price-alerts.index'),
            ]);
    }
}
