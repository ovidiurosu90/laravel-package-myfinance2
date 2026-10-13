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

        foreach ($this->_ordered($trades) as $trade) {
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

    /**
     * Trades in a deterministic order. The callers load them ORDER BY timestamp only, so trades sharing
     * a timestamp came back in whatever order the query plan produced, and two queries could build
     * different windows from the same ledger. Within one timestamp and symbol:
     * - shares already held: SELLs first, so a same-second SELL and BUY of the whole position (e.g. a
     *   transfer between accounts) closes the window and opens a new one, instead of counting the
     *   re-bought shares as invested twice in one window;
     * - nothing held: BUYs first, so a same-second round trip from zero still opens its window.
     * Ties within the same action keep id order.
     *
     * @return array<int, mixed>
     */
    private function _ordered(Collection $trades): array
    {
        $sorted = $trades->all();
        usort($sorted, fn ($a, $b) => [$a->timestamp->format('Y-m-d H:i:s'), (int) ($a->id ?? 0)]
            <=> [$b->timestamp->format('Y-m-d H:i:s'), (int) ($b->id ?? 0)]);

        $groups = [];
        foreach ($sorted as $trade) {
            $groups[$trade->timestamp->format('Y-m-d H:i:s')][$trade->symbol][] = $trade;
        }

        $ordered = [];
        $held    = [];
        foreach ($groups as $bySymbol) {
            foreach ($bySymbol as $symbol => $group) {
                $sells = array_values(array_filter($group, fn ($t) => $t->action === 'SELL'));
                $other = array_values(array_filter($group, fn ($t) => $t->action !== 'SELL'));
                $isHeld = ($held[$symbol] ?? 0.0) > 0.0001;
                array_push($ordered, ...($isHeld ? array_merge($sells, $other) : array_merge($other, $sells)));
                foreach ($group as $t) {
                    $qty = (float) $t->quantity;
                    $held[$symbol] = ($held[$symbol] ?? 0.0) + match ($t->action) {
                        'BUY'   => $qty,
                        'SELL'  => -$qty,
                        default => 0.0,
                    };
                }
            }
        }

        return $ordered;
    }
}
