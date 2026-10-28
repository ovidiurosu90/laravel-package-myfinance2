@use('ovidiuro\myfinance2\App\Models\PeakProximityAlertEvent')
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@use('ovidiuro\myfinance2\App\Services\NearPeakReading')
@use('ovidiuro\myfinance2\App\Services\TierCalculationService')
@php
    $tierLabel = TierCalculationService::tierLabel($event->effective_tier);
    $tierKey   = strtoupper((string) $event->effective_tier);
    $tierIsBronze = $tierKey === 'BRONZE';
    $exitTiers = array_map('strtoupper', config('myfinance2.alerts.peak_proximity.exit_tiers', ['RUST', 'BRONZE']));
    $tierIsExit = in_array($tierKey, $exitTiers, true);

    // A range-bound alert (signal RANGE_HIGH / RANGE_LOW / RANGE_BUY) carries its reading in the summary: the same chip
    // as the watchlist, whatever the tier: TRIM near the high, ADD (BUY when not held)
    // near the low. Only the signals in myfinance2.alerts.peak_proximity.range_emails email (ADD and BUY by default).
    $summary    = is_array($event->summary) ? $event->summary : [];
    $isRange    = $event->isRangeSignal();
    $rangeBound = $isRange ? ($summary['range_bound'] ?? null) : null;
    // A weak tier in profit carries its reading the same way, on its TAKE PROFIT alert (with the near-peak windows
    // as context, in place of a near-peak alert). A near-peak card shows the chip too when its snapshot carries the
    // reading (cards stored before TAKE PROFIT replaced the near-peak alert).
    $isTakeProfit = $event->isTakeProfitSignal();
    $takeProfit   = $isTakeProfit || !$isRange ? ($summary['take_profit'] ?? null) : null;
    // A tier signal (TierSignalChip) the same way: on its own alert (RARE_HIGH / RARE_LOW / DETERIORATING) and on
    // the symbol's near-peak alert; never with a range-bound or TAKE PROFIT reading (it defers to their chip).
    $isTierSignal = $event->isTierSignal();
    $tierSignal   = !$isRange && $takeProfit === null ? ($summary['tier_signal'] ?? null) : null;
    // A near-peak alert leads with its own chip (NearPeakReading, as on the watchlist), the take-profit or tier
    // signal chip it carries after it as context. One stored before its snapshot carried the chip is rebuilt from
    // the event: the quadrant's action only when it emails.
    $isActionable = $event->classification === PeakProximityAlertEvent::CLASS_ACTIONABLE;
    $nearPeak     = !$isRange && !$isTakeProfit && !$isTierSignal
        ? ($summary['near_peak'] ?? NearPeakReading::reading(
            array_filter(explode(',', (string) $event->triggered_windows)),
            $event->closest_proximity_pct !== null ? (float) $event->closest_proximity_pct : null,
            $event->effective_tier,
            $isActionable ? $event->head_action : null,
            $isActionable
        ))
        : null;

    // Tooltip explaining why each badge is what it is, consistent with the watchlist wording.
    $tierTip = $tierLabel
        ? $tierLabel . ' tier, settled over the last 12 months (the tier on the watchlist). '
            . ($isRange
                ? PeakProximityAlertEvent::signalLabel($event->signal) . '. '
                    . ($event->classification === PeakProximityAlertEvent::CLASS_ACTIONABLE
                        ? 'This range-bound alert emails whatever the tier.'
                        : 'For your awareness: this range-bound alert does not email.')
                : ($isTakeProfit
                    ? 'A weak tier with the open position in profit: a TAKE PROFIT alert, near peak or not.'
                    : ($isTierSignal
                        ? 'A tier signal: today\'s reading is rare against the last 12 months, or a lasting drop.'
                            . ($event->classification === PeakProximityAlertEvent::CLASS_ACTIONABLE
                                ? ' This signal emails whatever the tier.'
                                : ' For your awareness: tier signals do not email.')
                        : ($tierIsExit
                        ? 'Weak enough to be exit-worthy: a near-peak in a 6M/1Y/2Y window can email.'
                        : 'Too strong to email on a near-peak; shown here for awareness only.'))))
        : '';

    $severityTips = [
        'HIGH'   => 'High: two or more long windows near peak at once, or RSI overbought. Reminders escalate fastest.',
        'MEDIUM' => 'Medium: one long window (6M/1Y/2Y) near peak. Reminders about weekly.',
        'LOW'    => 'Low: informational only (a strong tier, or just the 3M context window near peak). No email.',
    ];
    if ($isRange) {
        $severityTips = [
            'HIGH'   => 'High: RSI agrees with the swing (overbought for a TRIM, oversold for an ADD or a BUY).',
            'MEDIUM' => 'Medium: a range-bound alert'
                . ($event->classification === PeakProximityAlertEvent::CLASS_ACTIONABLE
                    ? '; a reminder every ' . (int) config('myfinance2.alerts.peak_proximity.range_reminder_days', 7)
                        . ' days while the price stays in the zone.'
                    : ', shown here for your awareness (not emailed).'),
        ];
    }
    if ($isTakeProfit) {
        $severityTips = [
            'HIGH'   => 'High: a long window (6M/1Y/2Y) near peak, or RSI overbought: the price backs the sale.',
            'MEDIUM' => 'Medium: in profit on a weak tier, not near a long-window peak; a reminder every '
                . (int) config('myfinance2.alerts.peak_proximity.take_profit_reminder_days', 7) . ' days.',
        ];
    }
    if ($isTierSignal) {
        $reminderDays = (int) config('myfinance2.alerts.peak_proximity.tier_signal_reminder_days', 7);
        $severityTips = [
            'HIGH'   => 'High: a SELL / TRIM the price backs (a position of 5 % or more of the portfolio, RSI'
                . ' overbought, or a 6M/1Y/2Y window near peak).',
            'MEDIUM' => 'Medium: a SELL without that backing or an ADD / BUY (see the caution flags in the chip),'
                . ' with a reminder every ' . $reminderDays
                . ' days; or a REVIEW, emailed once (dismissing it holds while the drop lasts).',
            'LOW'    => 'Low: a TRIM without that backing; a reminder every '
                . $reminderDays . ' days.',
        ];
    }
    $severityTip = $severityTips[$event->severity] ?? '';

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
@endphp
<div class="card h-100">
    <div class="card-body py-2">
        {{-- Header: the symbol and its chips, Dismiss pinned top-right (it never wraps below the content). --}}
        <div class="d-flex align-items-start justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a class="fw-semibold"
                   href="https://finance.yahoo.com/quote/{{ $event->symbol }}" target="_blank">
                    {{ $event->symbol }}
                </a>
                @include('myfinance2::watchlistsymbols.tables.partials.superstar-star', [
                    'superstar' => $summary['superstar'] ?? null,
                ])
                @if (!empty($tierLabel))
                    <span class="badge {{ TierCalculationService::tierBadgeClass($event->effective_tier) }}"
                          @if ($tierIsBronze) style="background-color:#e67e22!important;"@endif
                          data-bs-toggle="tooltip" title="{{ $tierTip }}">
                        {{ $tierLabel }}
                    </span>
                @endif
                @include('myfinance2::watchlistsymbols.tables.partials.near-peak-chip', [
                    'nearPeak' => $nearPeak,
                ])
                @if ($rangeBound !== null)
                    @include('myfinance2::watchlistsymbols.tables.partials.range-bound-chip', [
                        'range' => $rangeBound,
                    ])
                @elseif ($takeProfit !== null)
                    @include('myfinance2::watchlistsymbols.tables.partials.take-profit-chip', [
                        'takeProfit' => $takeProfit,
                    ])
                @elseif ($tierSignal !== null)
                    @include('myfinance2::watchlistsymbols.tables.partials.tier-signal-chip', [
                        'tierSignal' => $tierSignal,
                    ])
                @endif
                <span class="badge {{ $severityClass[$event->severity] ?? 'bg-secondary' }}"
                      data-bs-toggle="tooltip" title="{{ $severityTip }}">
                    {{ $event->severity }}
                </span>
            </div>
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
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
        <div class="small text-muted mt-1">
            @if ($rangeBound !== null)
                @include('myfinance2::peakproximityalerts.inbox.partials.range-summary')
            @elseif ($isTakeProfit && $takeProfit !== null)
                @include('myfinance2::peakproximityalerts.inbox.partials.take-profit-summary')
            @elseif ($isTierSignal && $tierSignal !== null)
                @include('myfinance2::peakproximityalerts.inbox.partials.tier-signal-summary')
            @elseif (!empty($sumWindows))
                <span>
                    Current price
                    <strong class="text-nowrap">{!! $sumPrice !== null
                        ? MoneyFormat::get_formatted_price_display($cur, $sumPrice, true) : 'n/a' !!}</strong>,
                    near its peak in {{ $nearCount }} {{ $nearCount === 1 ? 'window' : 'windows' }}.
                    Each window's trigger target is the price that would put it near peak.
                </span>
                @include('myfinance2::peakproximityalerts.inbox.partials.peak-windows-table')
                @include('myfinance2::peakproximityalerts.inbox.partials.today-move')
                @include('myfinance2::peakproximityalerts.inbox.partials.unrealized-gain')
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
                @php
                    $seenAs = $isRange ? 'in zone' : ($isTakeProfit ? 'in profit' : 'near peak');
                    $seenAs = $isTierSignal ? 'signal seen' : $seenAs;
                @endphp
                &middot; last {{ $seenAs }} {{ $event->last_seen_at->format('Y-m-d') }}
            @endif
        </div>
    </div>
</div>
