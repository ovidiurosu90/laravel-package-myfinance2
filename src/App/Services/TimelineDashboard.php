<?php

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Services\FundingDashboard;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\Dividend;

class TimelineDashboard
{
    /**
     * Execute the job.
     *
     * @return array
     */
    public function handle()
    {
        $items = [];

        $fundingService = new FundingDashboard();
        $fundingData = $fundingService->handle(); // items & balances
        foreach ($fundingData['items'] as $fundingItem) {
            $debitText = $fundingItem['debit_transaction']
                ? $fundingItem['debit_transaction']->debitAccountModel->name . ' ('
                . $fundingItem['debit_transaction']->debitAccountModel
                    ->currency->iso_code . ') '
                . ' ' . $fundingItem['debit_transaction']->amount . ' '
                . $fundingItem['debit_transaction']->getCurrency()
                : $fundingItem['credit_transaction']->debitAccountModel->name . ' ('
                . $fundingItem['credit_transaction']->debitAccountModel
                    ->currency->iso_code . ') ';

            $creditText = $fundingItem['credit_transaction']
                ? $fundingItem['credit_transaction']->creditAccountModel->name
                . ' ('
                . $fundingItem['credit_transaction']->creditAccountModel
                    ->currency->iso_code . ') '
                . $fundingItem['credit_transaction']->amount . ' '
                . $fundingItem['credit_transaction']->getCurrency()
                : $fundingItem['debit_transaction']->creditAccountModel->name . ' ('
                . $fundingItem['debit_transaction']->creditAccountModel
                    ->currency->iso_code . ') ';

            $items[] = [
                'row_label' => 'Funding',
                'bar_label' => $debitText . ' => ' . $creditText,
                'tooltip'   => view('myfinance2::timeline.partials.funding-tooltip',
                                    $fundingItem),
                'start'     => $fundingItem['debit_transaction'] ?
                    $fundingItem['debit_transaction']->timestamp
                        ->format(trans('myfinance2::general.date-format')) :
                    $fundingItem['credit_transaction']->timestamp
                        ->format(trans('myfinance2::general.date-format')),
                'end'       => $fundingItem['credit_transaction'] ?
                    $fundingItem['credit_transaction']->timestamp
                        ->format(trans('myfinance2::general.date-format')) :
                    $fundingItem['debit_transaction']->timestamp
                        ->format(trans('myfinance2::general.date-format')),
            ];
        }

        $trades = Trade::with('accountModel', 'tradeCurrencyModel')->get();
        foreach ($trades as $trade) {
            $items[] = [
                'row_label' => 'Trade',
                'bar_label' => $trade->accountModel->name . ' ('
                    . $trade->accountModel->currency->iso_code . ') '
                    . $trade->action . ' ' . $trade->quantity . ' '
                    . $trade->symbol . ' for ' . $trade->getPrincipleAmount(),
                'tooltip'   => view('myfinance2::timeline.partials.trade-tooltip',
                                    ['trade' => $trade]),
                'start'     => $trade->timestamp,
                'end'       => $trade->timestamp,
            ];
        }

        $dividends = Dividend::with('accountModel', 'dividendCurrencyModel')->get();
        foreach ($dividends as $dividend) {
            $items[] = [
                'row_label' => 'Dividend',
                'bar_label' => $dividend->accountModel->name . ' ('
                    . $dividend->accountModel->currency->iso_code
                    . ') ' . $dividend->symbol . ' => ' . $dividend->amount,
                'tooltip'   => view(
                    'myfinance2::timeline.partials.dividend-tooltip',
                    ['dividend' => $dividend]),
                'start'     => $dividend->timestamp,
                'end'       => $dividend->timestamp,
            ];
        }

        usort($items, self::cmp_by_key('start'));
        return ['items' => $items];
    }

    public static function cmp_by_key($key)
    {
        return function ($a, $b) use ($key) {
            return strnatcmp($a[$key], $b[$key]);
        };
    }
}

