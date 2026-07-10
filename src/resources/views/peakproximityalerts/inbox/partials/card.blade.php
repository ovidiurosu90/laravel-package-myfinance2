@use('ovidiuro\myfinance2\App\Models\PeakProximityAlertEvent')
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@use('ovidiuro\myfinance2\App\Services\TierCalculationService')
@php
    $tierLabel = TierCalculationService::tierLabel($event->effective_tier);
    $tierKey   = strtoupper((string) $event->effective_tier);
    $tierIsBronze = $tierKey === 'BRONZE';
    $exitTiers = array_map('strtoupper', config('alerts.peak_proximity.exit_tiers', ['RUST', 'BRONZE']));
    $tierIsExit = in_array($tierKey, $exitTiers, true);

    // Tooltip explaining why each badge is what it is, consistent with the watchlist wording.
    $tierTip = $tierLabel
        ? $tierLabel . " tier, from the position's gain-based return. "
            . ($tierIsExit
                ? 'Weak enough to be exit-worthy: a near-peak in a 6M/1Y/2Y window can email.'
                : 'Too strong to email on a near-peak; shown here for awareness only.')
        : '';

    $ownedActionTooltips = [
        'ACCUMULATE' => 'Strong returns and low volatility; consider adding to your position.',
        'HOLD'       => 'Strong returns but high volatility; keep the position, avoid adding more.',
        'REDUCE'     => 'Low returns and low volatility; consider trimming your position.',
        'EXIT'       => 'Low returns and high volatility; consider closing the position.',
    ];
    $actionTip = $event->head_action
        ? trim(($ownedActionTooltips[$event->head_action] ?? '')
            . ' Shown for context; the action does not decide whether an email is sent.')
        : '';

    $severityTips = [
        'HIGH'   => 'High: two or more long windows near peak at once, or RSI overbought. Reminders escalate fastest.',
        'MEDIUM' => 'Medium: one long window (6M/1Y/2Y) near peak. Reminders about weekly.',
        'LOW'    => 'Low: informational only (a strong tier, or just the 3M context window near peak). No email.',
    ];
    $severityTip = $severityTips[$event->severity] ?? '';

    $summary    = is_array($event->summary) ? $event->summary : [];
    $sumWindows = $summary['windows'] ?? [];
    $cur        = $summary['currency'] ?? '€';
    $sumPrice   = $summary['price'] ?? null;
    $gainEur    = $summary['unrealized_gain_eur'] ?? null;
    $gainPct    = $summary['unrealized_gain_pct'] ?? null;
    $todayEur   = $summary['today_gain_eur'] ?? null;
    $todayPct   = $summary['today_gain_pct'] ?? null;

    // Near-peak window count. Newer snapshots list every window and carry near_count; older ones
    // stored only the triggered windows (each implicitly near), so fall back to their row count.
    $nearCount  = $summary['near_count']
        ?? count(array_filter($sumWindows, fn ($w) => $w['near'] ?? true));

    $signedPct = fn ($v) => $v === null
        ? 'n/a'
        : (($v > 0 ? '+' : '') . MoneyFormat::get_formatted_pct((float) $v) . '%');
@endphp
<div class="card h-100">
    <div class="card-body py-2">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a class="fw-semibold"
                       href="https://finance.yahoo.com/quote/{{ $event->symbol }}" target="_blank">
                        {{ $event->symbol }}
                    </a>
                    @if (!empty($tierLabel))
                        <span class="badge {{ TierCalculationService::tierBadgeClass($event->effective_tier) }}"
                              @if ($tierIsBronze) style="background-color:#e67e22!important;"@endif
                              data-bs-toggle="tooltip" title="{{ $tierTip }}">
                            {{ $tierLabel }}
                        </span>
                    @endif
                    @if (!empty($event->head_action))
                        <span class="badge {{ $actionClass[$event->head_action] ?? 'bg-secondary' }}"
                              data-bs-toggle="tooltip" title="{{ $actionTip }}">
                            {{ $event->head_action }}
                        </span>
                    @endif
                    <span class="badge {{ $severityClass[$event->severity] ?? 'bg-secondary' }}"
                          data-bs-toggle="tooltip" title="{{ $severityTip }}">
                        {{ $event->severity }}
                    </span>
                </div>
                <div class="small text-muted mt-1">
                    @if (!empty($sumWindows))
                        <span>
                            Current price
                            <strong class="text-nowrap">{!! $sumPrice !== null ? MoneyFormat::get_formatted_price_display($cur, $sumPrice, true) : 'n/a' !!}</strong>,
                            near its peak in {{ $nearCount }} {{ $nearCount === 1 ? 'window' : 'windows' }}.
                            Each window's trigger target is the price that would put it near peak.
                        </span>
                        <table class="table table-sm table-borderless mb-1 mt-1" style="width:auto;">
                            <thead>
                                <tr class="text-nowrap">
                                    <th class="pe-3 fw-semibold">Window</th>
                                    <th class="pe-3 fw-semibold">From peak</th>
                                    <th class="pe-3 fw-semibold">Peak price</th>
                                    <th class="pe-3 fw-semibold">Peak date</th>
                                    <th class="pe-3 fw-semibold">Trigger target</th>
                                    <th class="fw-semibold">To go</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sumWindows as $w)
                                    @php $isNear = $w['near'] ?? true; @endphp
                                    <tr class="text-nowrap @if ($isNear) table-success @endif">
                                        <td class="pe-3">{{ $w['label'] }}@if ($isNear) <span class="badge bg-success ms-1">near peak</span>@endif</td>
                                        <td class="pe-3">{{ $signedPct($w['prox'] ?? null) }}</td>
                                        <td class="pe-3">{!! ($w['peak'] ?? null) !== null ? MoneyFormat::get_formatted_price_display($cur, $w['peak'], true) : 'n/a' !!}</td>
                                        <td class="pe-3">{{ $w['date'] ?? 'n/a' }}</td>
                                        <td class="pe-3">
                                            @if (($w['target'] ?? null) !== null)
                                                {!! MoneyFormat::get_formatted_price_display($cur, $w['target'], true) !!}@if (($w['thr'] ?? null) !== null) <span class="text-muted">({{ $signedPct(-(float) $w['thr']) }})</span>@endif
                                            @else
                                                n/a
                                            @endif
                                        </td>
                                        <td>
                                            @if ($isNear)
                                                <span class="text-success">in zone</span>
                                            @elseif (($w['to_go'] ?? null) !== null)
                                                {{ $signedPct($w['to_go']) }}
                                            @else
                                                n/a
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if ($todayEur !== null)
                            <span class="d-block">
                                Today's move
                                <span class="text-nowrap">{!! MoneyFormat::get_formatted_gain('€', $todayEur) !!}</span>@if ($todayPct !== null) (<span class="text-nowrap">{!! MoneyFormat::get_formatted_gain('%', $todayPct) !!}</span>)@endif,
                                which nudged the position toward its peak today.
                            </span>
                        @endif
                        @if ($gainEur !== null)
                            <span class="d-block">
                                Unrealized gain
                                <span class="text-nowrap">{!! MoneyFormat::get_formatted_gain('€', $gainEur) !!}</span>@if ($gainPct !== null) (<span class="text-nowrap">{!! MoneyFormat::get_formatted_gain('%', $gainPct) !!}</span>)@endif,
                                what you would lock in by selling now.
                            </span>
                        @endif
                    @else
                        Near peak in <strong>{{ $windowsLabel($event->triggered_windows) }}</strong>.
                        @if (!empty($event->peak_dates))
                            <span class="d-block">Peaks: {{ $event->peak_dates }}</span>
                        @endif
                    @endif
                </div>
                <div class="small text-muted mt-1">
                    Opened {{ optional($event->opened_at)->format('Y-m-d') }}
                    @if ($event->last_emailed_at)
                        &middot; last emailed {{ $event->last_emailed_at->format('Y-m-d') }}
                        ({{ $event->email_count }}x)
                    @else
                        &middot; not emailed
                    @endif
                    @if ($event->last_seen_at)
                        &middot; last near peak {{ $event->last_seen_at->format('Y-m-d') }}
                    @endif
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                @if ($event->status === PeakProximityAlertEvent::STATUS_OPEN)
                    <form method="POST"
                          action="{{ route('myfinance2::peak-proximity-alerts.dismiss') }}"
                          onsubmit="return confirm('Dismiss this alert for {{ $event->symbol }}? It will move to the Dismissed list and stop email reminders.');">
                        @csrf
                        <input type="hidden" name="ids[]" value="{{ $event->id }}">
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            <i class="fa fa-fw fa-check" aria-hidden="true"></i> Dismiss
                        </button>
                    </form>
                @else
                    <span class="small text-muted text-nowrap">
                        Dismissed {{ optional($event->dismissed_at)->format('Y-m-d') }}
                    </span>
                @endif
            </div>
        </div>
    </div>
</div>
