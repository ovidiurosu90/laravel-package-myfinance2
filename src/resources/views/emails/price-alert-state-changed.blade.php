{{-- Price alert paused / resumed email: which alerts changed status, with the same alerts table as the created
     email plus the new status. --}}
@extends('myfinance2::emails.layouts.base')
@use('Illuminate\Support\Str')

@section('styles')
        .section.paused  { border-left-color: #ffc107; }
        .section.resumed { border-left-color: #28a745; }
@endsection

@section('title')
    @if ($actionRaw === 'paused')
        ⏸ {{ Str::plural('Alert', count($alerts)) }} Paused
    @else
        ▶ {{ Str::plural('Alert', count($alerts)) }} Resumed
    @endif
    ({{ count($alerts) }})
@endsection

@section('body')
    <div class="section {{ $actionRaw }}">
        <h2>Status change</h2>
        <p style="margin:0">
            @if (count($alerts) === 1)
                Alert <strong>#{{ $alerts[0]->id }}</strong>
                for <strong>{{ $alerts[0]->symbol }}</strong>
                has been <strong>{{ $actionRaw }}</strong>.
            @else
                <strong>{{ count($alerts) }}</strong> alerts have been
                <strong>{{ $actionRaw }}</strong>.
            @endif
        </p>
    </div>

    @include('myfinance2::emails.partials.price-alert-rows', ['showStatus' => true])

    @include('myfinance2::emails.partials.action-links', ['links' => [
        ['label' => '→ View All Alerts', 'url' => $dashboardUrl],
    ]])
@endsection

@section('footer-title', 'Price alerts')
