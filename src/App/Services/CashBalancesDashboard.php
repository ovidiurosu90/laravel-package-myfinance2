<?php

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\CashBalance;

class CashBalancesDashboard
{
    /**
     * Execute the job.
     *
     * @return array (item1, item2, ...)
     */
    public function handle()
    {
        $items = CashBalance::all();
        // LOG::debug('items'); LOG::debug($items);

        return $items;
    }
}

