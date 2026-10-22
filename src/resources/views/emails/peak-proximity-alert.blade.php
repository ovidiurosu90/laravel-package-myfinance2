{{-- Peak-proximity alert email (near peak, range-bound, take profit, tier signal): the header with the symbol, its
     tier and the headline, the Summary for the signal, the Performance, Quadrant and Open positions cards and the
     button row. Every derived value is prepared in the PeakProximityAlert mailable. --}}
@extends('myfinance2::emails.layouts.base')
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')

@php
    // Presentation helpers only; every derived value is prepared in the PeakProximityAlert mailable.
    $decode = fn ($s) => html_entity_decode((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $eur    = '€';
    $perf   = $quoteData['performance'] ?? [];
    $tech   = $quoteData['technical_indicators'] ?? [];
    $cur    = $decode($quoteData['tradeCurrencyModel']->display_code ?? $eur);
    $price  = isset($quoteData['price']) ? (float) $quoteData['price'] : null;
@endphp

@section('width', '1024')

@section('styles')
        tr.near-peak td { background: #eafaf0; }
        tr.near-peak td:first-child { border-left: 3px solid #28a745; }
        tr.in-zone td { background: #cff4fc; }
        tr.in-zone td:first-child { border-left: 3px solid #0dcaf0; }
        .range-note { color: #055160; background: #cff4fc; border: 1px solid #9eeaf9;
            border-radius: 4px; padding: 6px 8px; }
        .profit-note { color: #0f5132; background: #d1e7dd; border: 1px solid #a3cfbb;
            border-radius: 4px; padding: 6px 8px; }
        .signal-note { color: #664d03; background: #fff3cd; border: 1px solid #ffe69c;
            border-radius: 4px; padding: 6px 8px; }
        .acct-block { margin-top: 12px; padding-top: 12px; border-top: 1px solid #e0e0e0; }
        .acct-title { font-weight: 600; margin-bottom: 6px; }
@endsection

@section('title')
    &#128276; <a href="{{ $yahooUrl }}" target="_blank">{{ $symbol }}</a>
    @if (!empty($tierLabel))
        <span class="badge {{ $tierClass }}">{{ $tierLabel }}</span>
    @endif
    @if (!empty($superstar))
        <span style="color:#ffc107;" title="{{ $superstar['title'] }}">&#9733;</span>
    @endif
    {{ $headline }}
@endsection

@section('subtitle')
    @if (!empty($isReminder))Reminder &middot; @endif
    @if ($tierSummary !== ''){{ $tierSummary }} &middot; @endif
    {{ implode(' · ', $triggerSummary) }}
@endsection

@section('body')
    {{-- Summary: the range levels for a range-bound alert, the reading and the windows for a take-profit or a
         tier signal one, else every window near its peak --}}
    @if ($isRange)
        @include('myfinance2::emails.partials.range-bound-summary')
    @elseif ($isTakeProfit)
        @include('myfinance2::emails.partials.take-profit-summary')
    @elseif ($isTierSignal)
        @include('myfinance2::emails.partials.tier-signal-summary')
    @else
        @include('myfinance2::emails.partials.peak-proximity-summary')
    @endif

    @include('myfinance2::emails.partials.peak-performance-card')
    @include('myfinance2::emails.partials.peak-quadrant-card')
    @include('myfinance2::emails.partials.peak-open-positions-card')

    @include('myfinance2::emails.partials.action-links')
@endsection

@section('footer-title')
    @if ($isRange)
        Peak-proximity alert, range-bound swing for {{ $symbol }}
    @elseif ($isTakeProfit)
        Peak-proximity alert, take profit on {{ $symbol }}
    @elseif ($isTierSignal)
        Peak-proximity alert, tier signal on {{ $symbol }}
    @else
        Peak-proximity exit hint for {{ $symbol }}
    @endif
@endsection

@section('footer')
    @if ($isRange)
        Sent because a range-bound {{ empty($rangeBound['is_owned']) ? 'symbol you traded before' : 'open position' }}
        is {{ $triggerSummary[0] }}.
    @elseif ($isTakeProfit)
        Sent because an open position on a weak tier ({{ $tierLabel }}) is {{ $triggerSummary[0] }}.
    @elseif ($isTierSignal)
        Sent because {{ $symbol }}'s tier reads {{ strtolower($tierSignal['label']) }} against the last 12 months
        ({{ $triggerSummary[0] }}).
    @else
        Sent because an open position is near a recent peak.
    @endif
    Sent at most once per day per symbol.
@endsection
