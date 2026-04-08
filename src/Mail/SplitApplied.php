<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

use ovidiuro\myfinance2\App\Models\StockSplit;
use ovidiuro\myfinance2\Mail\Concerns\HasAppLabel;

class SplitApplied extends Mailable
{
    use Queueable, SerializesModels, HasAppLabel;

    private StockSplit $_split;
    private array $_summary;

    /**
     * @param StockSplit $split
     * @param array      $summary  Output of ApplySplitService::apply():
     *                             trades_updated, alerts_adjusted, changed_trades, changed_alerts
     */
    public function __construct(StockSplit $split, array $summary)
    {
        $this->_split   = $split;
        $this->_summary = $summary;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $split        = $this->_split;
        $tradesCount  = $this->_summary['trades_updated'];
        $alertsCount  = $this->_summary['alerts_adjusted'];
        $label        = $this->_appLabel();

        $subject = "{$label} Split {$split->getRatioLabel()} for {$split->symbol} applied"
            . " — {$tradesCount} trade(s) updated, {$alertsCount} alert(s) adjusted";

        return $this->subject($subject)
            ->view('myfinance2::emails.split-applied')
            ->with([
                'split'        => $split,
                'summary'      => $this->_summary,
                'dashboardUrl' => route('myfinance2::stock-splits.index'),
            ]);
    }
}
