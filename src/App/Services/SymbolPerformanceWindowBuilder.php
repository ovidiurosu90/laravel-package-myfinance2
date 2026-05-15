<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class SymbolPerformanceWindowBuilder
{
    /**
     * Build position windows from all trades for each symbol.
     *
     * A window opens when the combined quantity across all accounts goes from 0 → positive,
     * and closes when the combined quantity returns to 0 (fully exited).
     *
     * @param Collection $trades All trades sorted by timestamp ascending
     * @return array symbol => [window, ...]  each window has: index, start_date, end_date, is_open, trades[]
     */
    public function build(Collection $trades): array
    {
        $result = [];
        $runningQty = [];
        $currentWindow = [];
        $windowIndex = [];

        foreach ($trades as $trade) {
            $symbol = $trade->symbol;

            if (!isset($runningQty[$symbol])) {
                $runningQty[$symbol] = 0.0;
                $result[$symbol] = [];
                $windowIndex[$symbol] = 0;
            }

            if ($trade->action === 'BUY') {
                if ($runningQty[$symbol] <= 0.0) {
                    $windowIndex[$symbol]++;
                    $currentWindow[$symbol] = [
                        'index'      => $windowIndex[$symbol],
                        'start_date' => $trade->timestamp,
                        'end_date'   => null,
                        'is_open'    => true,
                        'trades'     => [],
                    ];
                }
                $currentWindow[$symbol]['trades'][] = $trade;
                $runningQty[$symbol] += (float) $trade->quantity;
            } elseif ($trade->action === 'SELL') {
                if (!isset($currentWindow[$symbol])) {
                    Log::warning(
                        "SymbolPerformanceWindowBuilder: SELL without open window for {$symbol}"
                    );
                    continue;
                }
                $currentWindow[$symbol]['trades'][] = $trade;
                $runningQty[$symbol] -= (float) $trade->quantity;

                if ($runningQty[$symbol] < 0.0001) {
                    $currentWindow[$symbol]['end_date'] = $trade->timestamp;
                    $currentWindow[$symbol]['is_open'] = false;
                    $result[$symbol][] = $currentWindow[$symbol];
                    unset($currentWindow[$symbol]);
                    $runningQty[$symbol] = 0.0;
                }
            }
        }

        foreach ($currentWindow as $symbol => $window) {
            $result[$symbol][] = $window;
        }

        return $result;
    }
}
