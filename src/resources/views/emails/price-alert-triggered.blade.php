{{-- Price alert email (and its split-anomaly warning), laid out as the peak-proximity alert: the header with the
     symbol, its tier and the headline, a Summary (the price against the target, today's move, the unrealized gain),
     the position and the button row. Every value is prepared in the PriceAlertTriggered mailable. --}}
@extends('myfinance2::emails.layouts.base')
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')

@php
    $eur    = '€';
    $isLoss = $sellGainEur !== null && $sellGainEur < 0;
    $relativeTarget = [
        'alert'    => $alert,
        'currency' => $currencyLabel,
    ];
@endphp

@section('width', '1024')

@section('styles')
        .section.triggered { border-left-color: #28a745; }
        .section.loss { border-left-color: #dc3545; }
        .section.split-warning { border-left-color: #ffc107; background: #fffbec; }
@endsection

@section('title')
    @if ($isSplitWarning)&#9888;&#65039;@else&#128276;@endif
    <a href="https://finance.yahoo.com/quote/{{ $alert->symbol }}" target="_blank">{{ $alert->symbol }}</a>
    @if (!empty($tierLabel))
        <span class="badge {{ $tierClass }}">{{ $tierLabel }}</span>
    @endif
    {{ $headline }}
@endsection

@section('subtitle')
    @if ($tierSummary !== ''){{ $tierSummary }} &middot; @endif
    Now {{ MoneyFormat::get_formatted_price_display($currencyLabel, $currentPrice, true) }}
    vs target {{ MoneyFormat::get_formatted_price_display($currencyLabel, (float) $alert->target_price, true) }}
@endsection

@section('body')
    @if ($isSplitWarning)
    <div class="section split-warning">
        <h2>Possible stock split</h2>
        <p>The price alert for <strong>{{ $alert->symbol }}</strong> may be stale due to a possible stock split.</p>
        <p>
            Current price: <strong class="text-nowrap">{{ MoneyFormat::get_formatted_price($currentPrice, true) }}
                {!! $currencyHtml !!}</strong><br>
            Alert target: <strong class="text-nowrap">{{ MoneyFormat::get_formatted_price(
                (float) $alert->target_price, true) }} {!! $currencyHtml !!}</strong>
        </p>
        @include('myfinance2::emails.partials.price-alert-relative-target', $relativeTarget)
        <p>
            Please review and update your alert. You can re-run the suggestion engine to get a fresh target price
            using split-adjusted data.
        </p>
    </div>
    @else
    <div class="section {{ $isLoss ? 'loss' : 'triggered' }}">
        <h2>Summary</h2>
        <p>
            @if ($tierSummary !== '')<strong>{{ $tierSummary }}.</strong>@endif
            Current price
            <strong class="text-nowrap">{{ MoneyFormat::get_formatted_price($currentPrice, true) }}
                {!! $currencyHtml !!}</strong>
            is {{ $direction }} the alert target of
            <strong class="text-nowrap">{{ MoneyFormat::get_formatted_price((float) $alert->target_price, true) }}
                {!! $currencyHtml !!}</strong>.
        </p>
        @include('myfinance2::emails.partials.price-alert-relative-target', $relativeTarget)
        @include('myfinance2::emails.partials.today-move')
        @include('myfinance2::emails.partials.sell-gain')
        @if (!empty($alert->notes))
        <p style="margin-top:8px;"><span class="text-muted">Notes:</span> {{ $alert->notes }}</p>
        @endif
        <p class="text-muted" style="margin-top:8px;">
            Source: {{ ucfirst(str_replace('_', ' ', $alert->source)) }}, fired {{ $alert->trigger_count + 1 }}
            {{ ($alert->trigger_count + 1) == 1 ? 'time' : 'times' }}.
        </p>
    </div>

    @include('myfinance2::emails.partials.price-alert-position')
    @endif

    @include('myfinance2::emails.partials.action-links')
@endsection

@section('footer-title', "Price alert #{$alert->id} for {$alert->symbol}")

@section('footer')
    Sent at most once per day per alert, until you pause or delete it.
@endsection
