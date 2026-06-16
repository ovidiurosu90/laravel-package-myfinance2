@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@use('ovidiuro\myfinance2\App\Services\DipBuyingBacktestService')
@extends('layouts.app')
@section('template_title', 'Dip Buying Plan Backtest')
@section('template_linked_css')
    @include('myfinance2::general.partials.styles')
@endsection
@php
    $num = fn ($v) => MoneyFormat::get_formatted_number_plain((float) $v, 0);
    $shortDate = fn ($d) => \Carbon\Carbon::parse($d)->format("d M 'y");
    $plural = fn ($n, $word) => ((int) $n) . ' ' . \Illuminate\Support\Str::plural($word, (int) $n);
    $pct = function ($v) {
        if ($v === null) {
            return 'n/a';
        }
        return ($v >= 0 ? '+' : '') . MoneyFormat::get_formatted_pct((float) $v) . '%';
    };
@endphp
@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                @include('myfinance2::dipbuyingalerts.partials.drawdown-chart-card')
                <div class="clearfix mb-3"></div>
                <div class="card card-default">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Self-validation backtest</span>
                        @include('myfinance2::dipbuyingalerts.partials.subnav', ['active' => 'backtest'])
                    </div>
                    <div class="card-body">
                        <div class="text-muted small mb-2">
                            <span class="fw-semibold">Window:</span>
                            {{ $report['from'] }} &rarr; {{ $report['to'] }} &middot;
                            <span class="fw-semibold">Counts as a drop:</span>
                            {{ (float) $report['min_drop'] }}% or more on
                            {{ DipBuyingBacktestService::dropModes()[$report['drop_mode']] ?? $report['drop_mode'] }} &middot;
                            <span class="fw-semibold">Pool:</span>
                            @if ($report['pool_source'] === 'override')
                                fixed at &euro;{{ $num($report['pool_eur']) }}
                            @else
                                your cash at the start of each episode
                            @endif
                        </div>
                        <p class="text-muted small">
                            For each drop, this replays your own trades through the same ladder the live
                            tool uses, then compares what you actually put in against what the ladder
                            would have had you deploy. It is a check on your own history only, a personal
                            sanity check rather than statistical proof.
                        </p>

                        @php
                            $statusMeta = [
                                'good'    => ['border-success',  'bg-success',          'On track',     'fa-check-circle'],
                                'average' => ['border-warning',  'bg-warning text-dark', 'Could improve', 'fa-exclamation-circle'],
                                'bad'     => ['border-danger',   'bg-danger',           'Mistake',      'fa-times-circle'],
                            ];
                        @endphp

                        @if (!empty($report['current_episode']))
                            @include('myfinance2::dipbuyingalerts.partials.current-episode-card', [
                                'ce'        => $report['current_episode'],
                                'firstBand' => $report['first_band'] ?? null,
                            ])
                        @endif

                        @forelse ($report['episodes'] as $i => $ep)
                            @php
                                $a    = $ep['assessment'];
                                $meta = $statusMeta[$a['status']] ?? $statusMeta['average'];
                                // Positive entry_dd_delta => the ladder would have bought lower (better).
                                $deltaTxt = ($a['entry_dd_delta'] >= 0 ? '+' : '') . $a['entry_dd_delta'];
                                $gapTxt   = ($a['deploy_gap_pct'] >= 0 ? '+' : '') . $a['deploy_gap_pct'];
                                // EUR equivalent of the deployment gap (signed like the points figure).
                                $gapEur    = $a['deploy_gap_pct'] / 100.0 * (float) $ep['pool_eur'];
                                $gapEurTxt = ($gapEur >= 0 ? '+' : '-') . '€' . $num(abs($gapEur));
                            @endphp
                            <div class="card mb-3 border-start border-4 {{ $meta[0] }}">
                                <div class="card-body">
                                    <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                                        <span class="fw-semibold">Episode {{ $i + 1 }}</span>
                                        <span class="text-muted small">
                                            peak {{ $ep['peak_date'] }} &rarr; low {{ $ep['low_date'] }}
                                            (-{{ $ep['max_dd'] }}%) &middot; pool &euro;{{ $num($ep['pool_eur']) }}
                                        </span>
                                        <span class="badge {{ $meta[1] }} ms-auto">
                                            <i class="fa {{ $meta[3] }}" aria-hidden="true"></i> {{ $meta[2] }}
                                        </span>
                                        @if ($ep['early_exhaustion'])
                                            <span class="badge bg-danger">early exhaustion</span>
                                        @endif
                                        @if ($ep['cash_drag'])
                                            <span class="badge bg-warning text-dark">cash drag</span>
                                        @endif
                                        @if ($ep['guided']['target_pct'] <= 0)
                                            <span class="badge bg-secondary" data-bs-toggle="tooltip"
                                                  title="Shallower than the ladder's first band, so the guided plan deploys nothing here. Shown for context.">
                                                below ladder
                                            </span>
                                        @endif
                                    </div>

                                    <div class="mb-3 {{ $a['status'] === 'good' ? 'text-success' : ($a['status'] === 'bad' ? 'text-danger' : 'text-warning-emphasis') }}">
                                        {{ $a['headline'] }}
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <div class="label text-muted small text-uppercase fw-bold">
                                                Your actual
                                            </div>
                                            <div class="small">
                                                <span data-bs-toggle="tooltip"
                                                      title="Total you bought during this drop, gross of any sells. The live current-episode figure is Net deployed instead (cash actually drawn down, so sells reduce it), which is why the two can differ.">Deployed</span>
                                                <span class="fw-semibold">&euro;{{ $num($ep['actual']['deployed_eur']) }}</span>
                                                ({{ $ep['actual']['deployed_pct'] }}% of &euro;{{ $num($ep['pool_eur']) }})
                                                @if ($ep['actual']['exhaustion_dd'] !== null)
                                                    <span class="text-muted">all-in by -{{ $ep['actual']['exhaustion_dd'] }}%</span>
                                                @endif
                                            </div>
                                            <div class="small">
                                                Avg entry drawdown -{{ $ep['actual']['avg_entry_dd'] }}%
                                                <span class="text-muted">({{ $plural($ep['actual']['buy_count'], 'buy') }})</span>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="label text-muted small text-uppercase fw-bold">
                                                Guided ladder
                                            </div>
                                            <div class="small">
                                                Target
                                                <span class="fw-semibold">&euro;{{ $num($ep['guided']['target_eur']) }}</span>
                                                ({{ $ep['guided']['target_pct'] }}% of &euro;{{ $num($ep['pool_eur']) }})
                                            </div>
                                            <div class="small">
                                                Avg entry drawdown -{{ $ep['guided']['avg_entry_dd'] }}%
                                            </div>
                                            <div class="small text-muted">
                                                Reserve kept: &euro;{{ $num($ep['guided']['reserve_kept_eur']) }}
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="label text-muted small text-uppercase fw-bold">
                                                How far off
                                            </div>
                                            <div class="small">
                                                <span data-bs-toggle="tooltip"
                                                      title="Difference in average entry drawdown, in drawdown points. Positive = the ladder would have bought lower (deeper in the drop) than you did.">Entry depth:</span>
                                                <span class="fw-semibold">{{ $deltaTxt }}</span> drawdown pts
                                            </div>
                                            <div class="small">
                                                <span data-bs-toggle="tooltip"
                                                      title="Gap between the ladder's target and what you deployed, as percentage points of the pool and the EUR equivalent. Positive = you deployed less than the ladder target at this depth.">Deployed vs target:</span>
                                                <span class="fw-semibold">{{ $gapTxt }}</span> pts of pool
                                                ({{ $gapEurTxt }})
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="alert alert-secondary">No drawdown episodes in this window.</div>
                        @endforelse

                        <div class="mt-3" style="max-width: 28rem;">
                            <div class="label text-muted small text-uppercase fw-bold mb-1">
                                Baselines (the bars to clear)
                            </div>
                            <table class="table table-sm">
                                <tbody>
                                    <tr>
                                        <td>Stay fully invested</td>
                                        <td class="text-end">{{ $pct($report['baselines']['stay_invested_pct']) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Monthly DCA</td>
                                        <td class="text-end">{{ $pct($report['baselines']['dca_pct']) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Guided ladder</td>
                                        <td class="text-end">{{ $pct($report['baselines']['guided_pct']) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="alert alert-info mt-2">
                            <i class="fa fa-info-circle" aria-hidden="true"></i> {{ $report['headline'] }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="clearfix mb-4"></div>
    </div>
@endsection
@section('footer_scripts')
    @include('myfinance2::general.scripts.tooltips')
    @include('myfinance2::dipbuyingalerts.scripts.drawdown-chart')
@endsection
