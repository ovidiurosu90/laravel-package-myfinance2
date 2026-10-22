{{-- Price alert created email (one manual alert, or the suggestion engine's batch): what was created and each
     alert's target and accounts. --}}
@extends('myfinance2::emails.layouts.base')
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@use('Illuminate\Support\Str')

@section('styles')
        .section { border-left-color: #0d6efd; }
        .section.suggestion { border-left-color: #0dcaf0; }
@endsection

@section('title')
    @if ($source === 'suggestion')
        ✅ Alert Suggestions Created ({{ count($alerts) }})
    @else
        ✅ Price Alert Created
    @endif
@endsection

@section('body')
    <div class="section {{ $source === 'suggestion' ? 'suggestion' : '' }}">
        @if ($source === 'suggestion')
            @php $thresholdPct = config('alerts.suggestion_threshold_pct', 3); @endphp
            <h2>Suggestion engine</h2>
            <p style="margin:0">
                The suggestion engine created <strong>{{ count($alerts) }}</strong>
                new ▲ PRICE_ABOVE {{ Str::plural('alert', count($alerts)) }} from your open positions, each targeting
                <strong>{{ MoneyFormat::get_formatted_pct_compact($thresholdPct) }}&nbsp;%</strong> below the 2-year
                high.
            </p>
        @else
            @php $alert = $alerts[0]; @endphp
            <h2>New alert</h2>
            <p style="margin:0">
                A new price alert has been created for
                <strong>{{ $alert->symbol }}</strong>.
            </p>
        @endif
    </div>

    @include('myfinance2::emails.partials.price-alert-rows', ['showStatus' => false])

    @include('myfinance2::emails.partials.action-links', ['links' => [
        ['label' => '→ View All Alerts', 'url' => $dashboardUrl],
    ]])
@endsection

@section('footer-title', 'Price alerts')
