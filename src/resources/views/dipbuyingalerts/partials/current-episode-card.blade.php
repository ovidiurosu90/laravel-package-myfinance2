@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@use('ovidiuro\myfinance2\App\Services\DipBuyingPresenter')
{{-- The in-progress current drop, measured from the most recent local peak (the blue overlay on the
     chart). Shared by the backtest report and the /positions Dip Buying panel so both read this dip
     from one computation: same pool (cash at the local peak), same net-deployed (cash drawn down),
     same guided target. Expects $ce (current_episode) and $firstBand (first deploying band, or null).
     Every display decision (verdict, recommendation, all-time-high suffix) comes from
     DipBuyingPresenter so the card and the email read one source. --}}
@php
    $num    = fn ($v) => MoneyFormat::get_formatted_number_plain((float) $v, 0);
    $colors = DipBuyingPresenter::colors();
    $cAllTime = $colors['effective']; // red: the effective drawdown from the all-time high
    $cLocal   = $colors['local'];     // blue: the drop from the most recent local peak

    $runningDd = $ce['running_dd'] ?? null;
    $peakDd    = $ce['peak_dd'] ?? null;
    $athSuffix = DipBuyingPresenter::athSuffix($ce);

    $verdict = DipBuyingPresenter::ceVerdict($ce);
    $ceTarget   = $verdict['target_pct'];
    $ceDeployed = $verdict['deployed_pct'];
    $ceGapPct   = $verdict['gap_pct'];
    $ceGapEur   = $verdict['gap_eur'];
    // Card badge class per verdict key (web presentation only).
    $verdictBadge = ['behind' => 'bg-danger', 'ahead' => 'bg-success', 'on_plan' => 'bg-success'];

    $rec     = DipBuyingPresenter::recommendation($ce, $firstBand);
    $labels  = DipBuyingPresenter::ceLabels($ce);
    $deepest = $ce['max_dd'] > $ce['current_dd']
        ? '; deepest -' . $ce['max_dd'] . '% on ' . DipBuyingPresenter::shortDate($ce['low_date'])
        : '';
@endphp
<div class="card {{ $cardClass ?? 'mb-3' }} border-start border-4 border-primary">
    <div class="card-body">
        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
            <span class="fw-semibold" style="color:{{ $cLocal }};">{{ $labels['title'] }}</span>
            <span class="text-muted small">
                now <span style="color:{{ $cLocal }};">-{{ $ce['current_dd'] }}%</span>
                from {{ $labels['peak'] }} on {{ DipBuyingPresenter::shortDate($ce['peak_date']) }}{{ $deepest }}.
                Cash pool &euro;{{ $num($ce['pool_eur']) }} as of {{ DipBuyingPresenter::shortDate($ce['peak_date']) }}
                <i class="fa fa-info-circle" aria-hidden="true"
                   data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide"
                   title="The pool here is your actual cash across all accounts on
                          {{ DipBuyingPresenter::shortDate($ce['peak_date']) }}, the start of this dip, read from your
                          overview cash series. It is the cash you had available to deploy when the dip began, so every
                          target on the ladder is priced on it. The fallback pool on the Dip Buying settings page is
                          used instead only when no cash is recorded for this date. The settings page shows the ladder
                          on both pools."></i>.
            </span>
            <span class="badge bg-primary ms-auto" data-bs-toggle="tooltip" title="{{ $labels['badge_tip'] }}">
                <i class="fa fa-location-arrow" aria-hidden="true"></i> in progress
            </span>
            @if ($ceTarget > 0)
                <span class="badge {{ $verdictBadge[$verdict['key']] ?? 'bg-secondary' }}" data-bs-toggle="tooltip"
                      title="Your pace versus the ladder at this depth: the gap between its target ({{ (int) $ceTarget }}%) and what you have deployed ({{ round($ceDeployed, 1) }}%).">
                    {{ $verdict['label'] }}
                </span>
            @else
                <span class="badge bg-secondary" data-bs-toggle="tooltip"
                      title="Shallower than the ladder's first band, so the guided plan deploys nothing yet.">
                    Below the ladder
                </span>
            @endif
        </div>

        @if (!is_null($runningDd))
            {{-- Two rulers for "how deep is this dip", and how they connect. Colors match the chart:
                 red = the effective drawdown line (from the all-time high), blue = the local drop band. --}}
            <div class="small mb-3">
                <div class="fw-semibold mb-1">Two rulers for this dip</div>
                <div>
                    <i class="fa fa-circle" style="color:{{ $cAllTime }};font-size:0.6rem;"
                       aria-hidden="true"></i>
                    <span class="fw-semibold" style="color:{{ $cAllTime }};">-{{ $runningDd }}%</span>
                    below your all-time high
                    <span class="text-muted">(the effective drawdown the ladder deploys on)</span>
                </div>
                <div>
                    <i class="fa fa-circle" style="color:{{ $cLocal }};font-size:0.6rem;"
                       aria-hidden="true"></i>
                    <span class="fw-semibold" style="color:{{ $cLocal }};">-{{ $ce['current_dd'] }}%</span>
                    below {{ $labels['peak'] }} on {{ DipBuyingPresenter::shortDate($ce['peak_date']) }}
                    <span class="text-muted">({{ $labels['pullback'] }})</span>
                </div>
                @if (!is_null($peakDd))
                    <div class="text-muted mt-1">
                        That peak, on {{ DipBuyingPresenter::shortDate($ce['peak_date']) }}, was itself
                        -{{ $peakDd }}% below the all-time high{!! $athSuffix !!}.
                    </div>
                @endif
            </div>
        @endif

        <div class="mb-3" style="color:{{ $cLocal }};">
            @if ($rec['kind'] === 'wait')
                Recommendation: <strong>{{ $rec['lead'] }}</strong>. {{ $rec['detail'] }}
                @if ($rec['first_band_sentence'])
                    <br class="d-xxl-none">{{ $rec['first_band_sentence'] }}
                @endif
            @else
                {{ $rec['text'] }}
            @endif
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <div class="label text-muted small text-uppercase fw-bold">
                    Your actual (this dip)
                </div>
                <div class="small">
                    @php($deployedTip = DipBuyingPresenter::deployedTooltip($ce))
                    @if ($deployedTip)
                        <span data-bs-toggle="tooltip" data-bs-html="true" data-bs-custom-class="tooltip-wide"
                              title="{{ $deployedTip }}">Net deployed</span>
                    @else
                        <span data-bs-toggle="tooltip"
                              title="Cash actually consumed since the local peak: your cash at the peak minus your cash
                              now, so sells that returned money reduce it (not the gross sum of
                              buys).">Net deployed</span>
                    @endif
                    <span class="fw-semibold">&euro;{{ $num($ce['actual']['deployed_eur']) }}</span>
                    ({{ $ce['actual']['deployed_pct'] }}% of &euro;{{ $num($ce['pool_eur']) }})
                </div>
                @if ($deployedNote = DipBuyingPresenter::deployBasis([], $ce)['note'])
                    <div class="small text-muted">{{ $deployedNote }}</div>
                @endif
                <div class="small">
                    Avg entry drawdown -{{ $ce['actual']['avg_entry_dd'] }}%
                    <span class="text-muted">({{ DipBuyingPresenter::plural($ce['actual']['buy_count'], 'buy') }}, {{ DipBuyingPresenter::plural($ce['actual']['sell_count'] ?? 0, 'sell') }})</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="label text-muted small text-uppercase fw-bold">
                    Guided ladder (now)
                </div>
                <div class="small">
                    Target
                    <span class="fw-semibold">&euro;{{ $num($ce['guided']['target_eur']) }}</span>
                    ({{ $ce['guided']['target_pct'] }}% of &euro;{{ $num($ce['pool_eur']) }})
                </div>
                <div class="small">
                    Avg entry drawdown -{{ $ce['guided']['avg_entry_dd'] }}%
                </div>
                <div class="small text-muted">
                    Reserve kept: &euro;{{ $num($ce['guided']['reserve_kept_eur']) }}
                </div>
            </div>
            <div class="col-md-4">
                <div class="label text-muted small text-uppercase fw-bold">
                    Deploy more?
                </div>
                <div class="small">
                    <span data-bs-toggle="tooltip"
                          title="Gap between the ladder's target at this depth and what you have deployed in this dip, as percentage points of the pool and the EUR equivalent. Positive = room to deploy more.">Deployed vs target:</span>
                    <span class="fw-semibold">{{ ($ceGapPct >= 0 ? '+' : '') . round($ceGapPct, 1) }}</span> pts of pool
                    ({{ ($ceGapEur >= 0 ? '+' : '-') . '€' . $num(abs($ceGapEur)) }})
                </div>
            </div>
        </div>
    </div>
</div>
