{{-- How to read the tier tables: allocation, tier rule and the Gain/y rows. Shown under the tables, inside the
     "Holdings by tier" collapse. --}}
<div class="mt-2 small text-muted" style="line-height:1.6">
    <div>
        <span class="fw-semibold text-body">Allocation:</span> by
        <span class="fw-semibold" style="color:{{ $health_score['mvalue_color'] }}">mvalue</span>, what each
        holding is worth today. Caution when Platinum + Gold is under its target, Warning when Bronze + Rust
        is over its limit. <span class="fw-semibold" style="color:{{ $health_score['cost_color'] }}">cost</span>
        shows what was put in.
    </div>
    <div>
        <span class="fw-semibold text-body">Tier:</span>
        @include('myfinance2::watchlistsymbols.tables.partials.health-score-tier-rules')
    </div>
    <div>
        <span class="fw-semibold text-body">Gain/y rows:</span> open position (muted), overall
        <span data-bs-toggle="tooltip" title="Compound Annual Growth Rate: the steady yearly rate that
            compounds to the actual total return.">CAGR</span>,
        market 1Y today (Market Snapshot), settled (Market Settled: the median of 12 months), then
        <span data-bs-toggle="tooltip" title="Money-weighted return: how your actual euros did, given the
            timing and size of every buy, sell and dividend.">XIRR</span>
        and vs S&amp;P 500 (your CAGR minus VUSA.AS's over the same dates). The bold row, settled, is the
        figure the tier is read from (* when a stand-in was used, see its tooltip).
    </div>
</div>
