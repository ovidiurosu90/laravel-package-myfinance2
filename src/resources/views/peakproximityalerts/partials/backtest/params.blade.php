{{-- Signal backtest: what was replayed and how (the method and its caveats), and the parameter cell to show (rare
     share x consistency, the plan's grid). Reads $report. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@use('ovidiuro\myfinance2\App\Services\TierSignalBacktestService')
@php
    $num  = fn ($v) => MoneyFormat::get_formatted_number_plain((float) $v, 0);
    $cfgRare = (float) config('watchlistsymbols.tier_history.rare_share_pct', 20);
    $cfgCons = (float) config('watchlistsymbols.tier_history.consistency_pct', 50);
@endphp
<form method="GET" action="{{ route('myfinance2::peak-proximity-alerts.backtest') }}"
      class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <label for="bt-rare" class="small text-muted mb-0">Rare share</label>
    <select id="bt-rare" name="rare_share" class="form-select form-select-sm" style="width:auto">
        @foreach (TierSignalBacktestService::GRID_RARE_SHARE as $value)
            <option value="{{ $value }}" @selected((float) $value === (float) $report['rare_share'])>
                {{ $value }} %{{ (float) $value === $cfgRare ? ' (in use)' : '' }}
            </option>
        @endforeach
    </select>
    <label for="bt-cons" class="small text-muted mb-0">Consistency</label>
    <select id="bt-cons" name="consistency" class="form-select form-select-sm" style="width:auto">
        @foreach (TierSignalBacktestService::GRID_CONSISTENCY as $value)
            <option value="{{ $value }}" @selected((float) $value === (float) $report['consistency'])>
                {{ $value }} %{{ (float) $value === $cfgCons ? ' (in use)' : '' }}
            </option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-sm btn-outline-primary">
        <i class="fa fa-fw fa-play" aria-hidden="true"></i> Run
    </button>
    <a class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip"
       title="Results are kept for an hour; this reruns the backtest on the latest history"
       href="{{ route('myfinance2::peak-proximity-alerts.backtest', [
           'rare_share' => $num($report['rare_share']), 'consistency' => $num($report['consistency']), 'refresh' => 1,
       ]) }}">
        <i class="fa fa-fw fa-refresh" aria-hidden="true"></i> Refresh
    </a>
</form>
<div class="text-muted small mb-2">
    <span class="fw-semibold">Replayed:</span>
    {{ $report['from'] }} &rarr; {{ $report['to'] ?? 'n/a' }} &middot;
    {{ $num($report['days']) }} symbol-days &middot; {{ $report['symbols'] }} symbols
    @if (!empty($report['missing_prices']))
        &middot; no closes, left out: {{ implode(', ', $report['missing_prices']) }}
    @endif
</div>
<div class="alert alert-light border small py-2 mb-3">
    Each past weekday is read the way the watchlist reads today: its tier against the 12 months before it only (no
    look-ahead), giving a <strong>rare high</strong> (SELL / TRIM), a <strong>rare low</strong> (ADD / BUY) or a
    <strong>deterioration</strong> (REVIEW); and, from what was stored that day, its <strong>range-bound</strong>
    (TRIM / ADD / BUY) or <strong>TAKE PROFIT</strong> reading. Consecutive days with the same signal make one
    episode, judged from its first day: the symbol's return over the next 21, 63 and 126 trading days, less VUSA.AS's
    over the same dates, in points. A sell (rare high, deterioration, TRIM, TAKE PROFIT) <strong>worked</strong> when
    the symbol then trailed VUSA.AS, a buy (rare low, ADD, BUY) when it beat it. The baseline row is the same
    comparison for every evaluated day, signal or not: a signal only helps when it works more often than that.
    <span class="d-block mt-1">
        One path through one period, with short, overlapping episodes that move together with the market: a sanity
        check, not proof. The full 12-month lookback exists only from a year after the history starts, and the
        settled tier needs {{ (int) config('watchlistsymbols.tier_history.min_history_days', 60) }} stored days. Every
        cell of the grid: <code>php artisan finance:tier-signal-backtest --user-id={{ auth()->id() }} --grid</code>.
    </span>
</div>
