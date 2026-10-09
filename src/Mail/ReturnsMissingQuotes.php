<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

use ovidiuro\myfinance2\Mail\Concerns\HasAppLabel;

/**
 * Sent by the returns refresh cron (ReturnsMissingQuoteNotifier) when held positions are valued at 0
 * because no price was found, typically a delisted symbol whose history the finance API dropped.
 */
class ReturnsMissingQuotes extends Mailable
{
    use Queueable, SerializesModels, HasAppLabel;

    /** @var array<int, array> year => positions from ReturnsMissingQuoteAlerts */
    private array $_positionsByYear;

    public function __construct(array $positionsByYear)
    {
        ksort($positionsByYear);
        $this->_positionsByYear = $positionsByYear;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $symbols = [];
        foreach ($this->_positionsByYear as $positions) {
            foreach ($positions as $position) {
                $symbols[$position['symbol']] = true;
            }
        }

        $subject = $this->_appLabel() . ' Returns: no price for ' . implode(', ', array_keys($symbols))
            . ', valued at 0 in ' . implode(', ', array_keys($this->_positionsByYear));

        $returnsLinks = [];
        foreach (array_keys($this->_positionsByYear) as $year) {
            $returnsLinks[$year] = route('myfinance2::returns.index', ['year' => $year]);
        }

        return $this->subject($subject)
            ->view('myfinance2::emails.returns-missing-quotes')
            ->with([
                'positionsByYear' => $this->_positionsByYear,
                'returnsLinks' => $returnsLinks,
            ]);
    }
}
