<?php

namespace ovidiuro\myfinance2\App\Services;

use Cache;

use Illuminate\Support\Facades\Log;
use Scheb\YahooFinanceApi\Results\Quote;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class MarketUtils
{
    private Quote $_quote;

    public function __construct(Quote $quote)
    {
        $this->_quote = $quote;
    }

    public function __toString()
    {
        $marketStatus = '';
        foreach ($this->getMarketStatus() as $key => $value) {
            $marketStatus .= "$key: $value<br />";
        }

        return sprintf("name: %s<br />state: %s<br />exhange_name: %s<br />"
            . "exchange_timezone: %s<br />%s",
            $this->getName(), $this->getState(), $this->getExchangeName(),
            $this->getExchangeTimezone(),
            $marketStatus
        );
    }

    public function getQuote()
    {
        return $this->_quote;
    }

    public function getName()
    {
        # us_market, fr_market, nl_market, gb_market
        return $this->_quote->getMarket();
    }

    public function getState()
    {
        return $this->_quote->getMarketState(); # CLOSED
    }

    public function getExchangeName()
    {
        # NasdaqGS, Paris, Amsterdam, LSE
        return $this->_quote->getFullExchangeName();
    }

    public function getExchangeTimezone()
    {
        # America/New_York, Europe/Paris, Europe/Amsterdam, Europe/London
        return $this->_quote->getExchangeTimezoneName();
    }
    public function timezoneInEurope()
    {
        return str_contains($this->_quote->getExchangeTimezoneName(),
            'Europe');
    }

    public function getMarketName()
    {
        # NYSE| NasdaqGS: M-F, 9:30am - 4:00pm (EDT)
        # XPAR| Paris: M-F, 9:00am - 5:30pm (CEST)
        # XAMS| Amsterdam: M-F, 9:00am - 5:30pm (CEST)
        # LSE|  LSE: M-F, 8:00am - 12:00pm, 12:02pm - 4:30pm (BST)

        $exchangeName = $this->getExchangeName();
        switch ($exchangeName) {
            case 'NYSE':
            case 'NasdaqGS':
                return 'NYSE';
            case 'Paris':
                return 'XPAR';
            case 'Amsterdam':
                return 'XAMS';
            case 'LSE':
                return 'LSE';
            default:
                return '';
        }
    }

    public function getMarketStatus()
    {
        $marketName = $this->getMarketName();
        $returnData = [
            'market_state' => $this->getState(),
            'status' => 'UNKNOWN',
        ];
        if ($marketName == '') {
            return $returnData;
        }
        $marketStatusString = '';

        $cacheKey = 'MARKET_STATUS_' . $marketName;
        if (Cache::has($cacheKey)) {
            $marketStatusString = Cache::get($cacheKey);
        } else {
            // LOG::debug('Getting market status for market ' . $marketName);
            $script = dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR .
                'scripts' . DIRECTORY_SEPARATOR . 'market_status.py';

            $command = [$script, $marketName];
            $process = new Process($command);
            $process->run();

            // executes after the command finishes
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $marketStatusString = trim($process->getOutput());
        }

        if ($marketStatusString == '') {
            return $returnData;
        }
        list($status, $open, $close) = explode(' ', $marketStatusString);

        if (!Cache::has($cacheKey)) {
            $retentionSeconds = 60 * 60 * 24; # 24 hours
            $timeToNextBell = (int)$open - time();
            if ($status == 'OPEN') {
                $timeToNextBell = (int)$close - time();
            }
            if ($timeToNextBell < $retentionSeconds) {
                 $retentionSeconds = $timeToNextBell;
            }

            Cache::put($cacheKey, $marketStatusString, $retentionSeconds);
            // LOG::debug("Stored marketStatusString '$marketStatusString' " .
            //     "into cached '$cacheKey' for $retentionSeconds seconds");
        }

        $returnData['status'] = $status;
        $returnData['open_timestamp'] = $open;
        $returnData['close_timestamp'] = $close;

        return $returnData;
    }

    public function getMarketStatusFormatted()
    {
        $text = 'UNKNOWN';
        $class = 'bg-danger';
        $tooltip = $this->__toString();
        $warning = '';
        $warningText = '';
        $countdown = '';
        $countdownClass = '';
        $countdownTimestamp = '';

        $data = $this->getMarketStatus();
        switch ($data['status']) {
            case 'OPEN':
                $text = 'OPEN';
                $class = 'bg-success';
                $countdownClass = 'market_status_countdown_to_close';
                $countdownTimestamp = $data['close_timestamp'];
                break;
            case 'CLOSED':
                $text = 'CLOSED';
                $class = 'bg-light text-dark';
                $countdownClass = 'market_status_countdown_to_open';
                $countdownTimestamp = $data['open_timestamp'];
                break;
            case 'UNKNOWN':
                if ($data['market_state'] == 'REGULAR') {
                    $text = 'OPEN';
                    $class = 'bg-success';
                    break;
                }
            default:
                $warningText .= "Unexpected market status!\n";
        }

        #NOTE Mapping market state to a known status
        if (!empty($data['market_state'])) {
            if ($data['market_state'] == 'PREPRE'
                || $data['market_state'] == 'PRE'
                || $data['market_state'] == 'POST'
                || $data['market_state'] == 'POSTPOST'
            ) {
                $data['market_state'] = 'CLOSED';
            }
            if ($data['market_state'] == 'REGULAR') {
                $data['market_state'] = 'OPEN';
                if ($data['status'] == 'UNKNOWN') {
                    $data['status'] = 'OPEN';
                }
            }
        }

        if (empty($data['market_state']) || empty($data['status']) ||
            $data['market_state'] != $data['status']
        ) {
            $warningText .= "Inconsistent market status!\n";
        }

        if ($warningText != '') {
            $warning = '<i class="btn p-0 m-0 fa fa-exclamation" '
                . 'data-bs-toggle="tooltip" title="' . $warningText
                . '" style="font-size: 24px;"></i>';
        }

        if ($countdownClass != '' && $countdownTimestamp != '') {
            $countdown = '<span class="' . $countdownClass
                . ' d-none small text-secondary" '
                . 'data-timestamp="' . $countdownTimestamp
                . '" data-bs-toggle="tooltip" '
                . 'title="'
                . date(trans('myfinance2::general.datetime-short-format'),
                       $countdownTimestamp)
                . '"></span>';
        }

        $output = $warning . '<div class="col-md-auto" style="min-width: 82px">'
            . '<span class="market_status text-left badge rounded-pill ' . $class
            . '" data-bs-toggle="tooltip" data-bs-custom-class="big-tooltips2" '
            . 'data-bs-html="true" title="<p class=\'text-left\'>'
            . $tooltip . '</p>">' . $text
            . '</span></div> <div class="col-md-auto p-0">' . $countdown . "</div>";

        return $output;
    }
}

