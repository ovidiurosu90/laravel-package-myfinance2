{{-- "How these alerts work", on the Manage and Inbox pages: the four kinds of alert (near peak, take profit,
     tier signal, range-bound), what gates each, and how reminders and the inbox work. Figures come from
     config/alerts.php and config/watchlistsymbols.php (range_bound, weak_tier_profit, tier_history), so the text
     follows any change there. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@php
    $periodMap   = ['3m' => '3M', '6m' => '6M', '1y' => '1Y', '2y' => '2Y'];
    $exitTiers   = config('alerts.peak_proximity.exit_tiers', ['RUST', 'BRONZE']);
    $exitLabels  = collect($exitTiers)->map(fn ($t) => ucfirst(strtolower((string) $t)))->implode(' or ');
    $meaningful  = config('alerts.peak_proximity.meaningful_windows', ['6m', '1y', '2y']);
    $longLabels  = collect($meaningful)->map(fn ($w) => $periodMap[$w] ?? strtoupper($w))->implode(' / ');
    $shortLabels = collect(config('alerts.peak_proximity.short_windows', ['3m']))
        ->map(fn ($w) => $periodMap[$w] ?? strtoupper($w))->implode(', ');
    $cadence     = config('alerts.peak_proximity.reminder_days_by_confluence', [1 => 7, 2 => 3, 3 => 1]);
    $cadenceText = collect($cadence)
        ->map(function ($days, $count) {
            $every = (int) $days === 1 ? 'daily' : "every {$days} days";
            $win   = (int) $count === 1 ? 'window' : 'windows';
            return "{$count} {$win} near peak: {$every}";
        })->implode('; ');
    $rangeDays   = (int) config('alerts.peak_proximity.range_reminder_days', 7);
    // The range-bound signals that email (alerts.peak_proximity.range_emails); the others are inbox cards only.
    $rangeKinds  = ['RANGE_HIGH' => 'TRIMs', 'RANGE_LOW' => 'ADDs', 'RANGE_BUY' => 'BUYs'];
    $rangeListed = array_flip((array) config('alerts.peak_proximity.range_emails', []));
    $rangeMail   = implode(', ', array_intersect_key($rangeKinds, $rangeListed));
    $rangeInbox  = implode(', ', array_diff_key($rangeKinds, $rangeListed));
    $rangeTop    = 100 - (int) config('watchlistsymbols.range_bound.near_high_pct', 70);
    $rangeBottom = (int) config('watchlistsymbols.range_bound.near_low_pct', 30);
    $profitTiers = collect(config('watchlistsymbols.weak_tier_profit.tiers', ['RUST', 'BRONZE']))
        ->map(fn ($t) => ucfirst(strtolower((string) $t)))->implode(' or ');
    $profitMin   = (float) config('watchlistsymbols.weak_tier_profit.min_gain_pct', 0);
    $profitText  = $profitMin > 0
        ? 'up more than ' . MoneyFormat::get_formatted_number_plain($profitMin, 0) . "\u{00A0}%"
        : 'in profit';
    $profitDays  = (int) config('alerts.peak_proximity.take_profit_reminder_days', 7);
    $signalDays  = (int) config('alerts.peak_proximity.tier_signal_reminder_days', 7);
    $rareShare   = (int) config('watchlistsymbols.tier_history.rare_share_pct', 20);
    $persistDays = (int) config('watchlistsymbols.tier_history.deteriorate_persist_days', 20);
    $sizeHigh    = (int) config('watchlistsymbols.tier_history.size_high_pct', 5);
    // The tier signals that email (alerts.peak_proximity.tier_signal_emails); the others are inbox cards only.
    $signalMail  = implode(', ', array_intersect_key(
        ['RARE_HIGH' => 'rare highs', 'RARE_LOW' => 'rare lows', 'DETERIORATING' => 'deteriorations'],
        array_flip((array) config('alerts.peak_proximity.tier_signal_emails', []))
    ));
@endphp
<div class="card border-info mb-2">
    <div class="card-body py-2">
        <p class="mb-1 fw-semibold">
            <i class="fa fa-fw fa-info-circle text-info" aria-hidden="true"></i>
            How these alerts work
        </p>
        <p class="small text-muted mb-1">
            Four kinds of alert share this page, the inbox and the history: <strong>near peak</strong>, an exit hint
            for a held position close to its peak; <strong>take profit</strong>
            (<i class="fas fa-sack-dollar fa-xs" aria-hidden="true"></i> TAKE PROFIT), for a held position in profit
            on a weak tier; <strong>tier signal</strong>
            (<i class="fas fa-caret-up fa-xs" aria-hidden="true"></i> rare high,
            <i class="fas fa-caret-down fa-xs" aria-hidden="true"></i> rare low,
            <i class="fas fa-arrow-trend-down fa-xs" aria-hidden="true"></i> deteriorating), when today's tier is
            unusual for the symbol; and <strong>range-bound</strong>
            (<i class="fas fa-arrows-up-down fa-xs" aria-hidden="true"></i> TRIM / ADD / BUY), for a stock you trade
            well that swings widely but ends the year about where it started. A range-bound symbol only gets
            range-bound alerts; a weak tier in profit gets a take-profit alert in place of the near-peak one; a symbol
            with a tier signal gets its tier signal and near-peak alerts.
        </p>
        <ul class="small text-muted mb-1 ps-3">
            <li>
                <strong>Off by default, opt-in per symbol.</strong> Nothing emails until you enable a symbol below:
                the ones you hold, then (under <strong>All</strong>) the ones you traded before, marked "not held".
                Each symbol can be enabled permanently or "until" a date.
            </li>
            <li>
                <strong>At most one email per symbol per day</strong>, whichever kind of alert.
            </li>
            <li>
                <strong>The inbox persists until you dismiss.</strong> Alerts stay in the
                <a href="{{ route('myfinance2::peak-proximity-alerts.inbox') }}">inbox</a> even after the condition
                has passed. Dismissing one ends its email reminders; if the condition returns, a fresh alert opens.
            </li>
        </ul>
        <p class="small fw-semibold text-body mb-0">Near-peak alerts (held positions)</p>
        <ul class="small text-muted mb-1 ps-3">
            <li>
                <strong>An exit aid, not a buy/hold signal.</strong> "Near peak" only matters for a position you
                would consider trimming, so an email needs a <strong>weak tier</strong> ({{ $exitLabels }}). Strong
                holdings (Platinum, Gold, Silver) never email; they show in the inbox as informational only. A weak
                tier in profit gets a take-profit alert instead (below).
            </li>
            <li>
                <strong>Tier gates, action does not.</strong> The tier in use (settled over the last 12 months, or
                your manual tier) decides whether a symbol can email. The HOLD / EXIT action is shown for context but
                never gates.
            </li>
            <li>
                <strong>{{ $longLabels }} matter; {{ $shortLabels }} is context.</strong> An email needs one of the
                longer windows near peak. A {{ $shortLabels }}-only signal is shown for context and never emails on
                its own.
            </li>
            <li>
                <strong>Escalating reminders.</strong> You get one email when an alert opens, then reminders that
                come faster the more long windows are near peak at once ({{ $cadenceText }}). A new window reaching
                peak emails right away. There is no cap; reminders continue until you dismiss the alert.
            </li>
        </ul>
        <p class="small fw-semibold text-body mb-0">
            <i class="fas fa-sack-dollar fa-xs" aria-hidden="true"></i> Take-profit alerts (held positions)
        </p>
        <ul class="small text-muted mb-1 ps-3">
            <li>
                <strong>A weak trend with a gain to keep.</strong> TAKE PROFIT is the chip on the watchlist row's Tier
                History line: the tier in use is {{ $profitTiers }} and your open position is {{ $profitText }}.
                Range-bound symbols are left out (they read TRIM near the high).
            </li>
            <li>
                <strong>Near peak or not.</strong> The alert opens while the condition holds; the 3M to 2Y windows
                are shown as context for the timing, in place of a separate near-peak alert. Severity is high when
                a {{ $longLabels }} window is near peak or RSI is overbought.
            </li>
            <li>
                <strong>Reminders.</strong> One email when an alert opens, another at once when a {{ $longLabels }}
                window newly reaches its peak, else a reminder every {{ $profitDays }} days, until you dismiss it.
            </li>
        </ul>
        <p class="small fw-semibold text-body mb-0">
            <i class="fas fa-caret-up fa-xs" aria-hidden="true"></i> Tier signal alerts (SELL / TRIM / ADD / BUY /
            REVIEW)
        </p>
        <ul class="small text-muted mb-1 ps-3">
            <li>
                <strong>Today against the last 12 months.</strong> The chip on the watchlist row's Tier History line:
                a <strong>rare high</strong> (today's tier above the settled one, reached on under
                {{ $rareShare }}&nbsp;% of the days) reads SELL on a Silver-or-weaker name, TRIM on a Gold one; a
                <strong>rare low</strong>
                (below a settled Gold or Platinum on under {{ $rareShare }}&nbsp;% of the days) reads ADD, or BUY for a
                symbol you traded before; <strong>deteriorating</strong> (two or more tiers down for
                {{ $persistDays }} trading days, so the tier follows today) reads REVIEW. Whatever the tier.
            </li>
            <li>
                <strong>One chip per symbol.</strong> A range-bound or TAKE PROFIT symbol gets no tier signal alert;
                its own chip and alert name the signal instead. A watch-only rare high or deterioration shows a chip
                but never alerts. Near a peak, the near-peak alert opens too; after a tier signal's email it waits
                for a later day.
            </li>
            <li>
                <strong>Severity and email.</strong> A SELL / TRIM is high when the price backs it (a position of
                {{ $sizeHigh }}&nbsp;% of the portfolio or more, RSI overbought, or a {{ $longLabels }} window near
                peak); an ADD / BUY lists caution flags (under MA200, Danger zone, a large position). Tier signals
                are inbox cards for your awareness, not emailed.
                @if ($signalMail !== '')
                    Except the {{ $signalMail }}: one email when an alert opens, for a SELL / TRIM another at once
                    when a {{ $longLabels }} window newly reaches its peak, else a reminder every {{ $signalDays }}
                    days, until you dismiss it (a deterioration only once).
                @endif
                A deterioration can last months: once dismissed it stays dismissed while that drop lasts; a new drop
                opens a new card.
            </li>
        </ul>
        <p class="small fw-semibold text-body mb-0">
            <i class="fas fa-arrows-up-down fa-xs" aria-hidden="true"></i> Range-bound alerts (TRIM / ADD / BUY)
        </p>
        <ul class="small text-muted mb-0 ps-3">
            <li>
                <strong>Read on the swing, not the tier.</strong> Range-bound is the chip on the watchlist row's
                Tier History line: you have traded it, its 1Y move is small, its 52-week range is wide and your own
                results in it are good. Its tier stays as measured and does not gate these alerts.
            </li>
            <li>
                <strong>Where the price sits in its 52-week range decides.</strong> Held: in the top
                {{ $rangeTop }}&nbsp;% an alert opens with <strong>TRIM</strong> (take the swing profit), in the
                bottom {{ $rangeBottom }}&nbsp;% with <strong>ADD</strong> (buy more on the dip). Traded before but
                not held: the bottom {{ $rangeBottom }}&nbsp;% opens a <strong>BUY</strong> (a good point to
                re-enter). Mid-range raises nothing; neither does the top without a position.
            </li>
            <li>
                <strong>Email and reminders.</strong>
                @if ($rangeMail !== '')
                    The {{ $rangeMail }}: one email when an alert opens, then a reminder every {{ $rangeDays }} days
                    while the price stays in that zone, until you dismiss it.
                @endif
                @if ($rangeInbox !== '')
                    The {{ $rangeInbox }}: an inbox card for your awareness, not emailed.
                @endif
                Severity is high when RSI agrees (overbought for a TRIM, oversold for an ADD or a BUY).
            </li>
        </ul>
    </div>
</div>
