@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@use('ovidiuro\myfinance2\App\Services\TierCalculationService')
@extends('layouts.app')
@section('template_title', 'Peak-Proximity Inbox')
@section('template_linked_css')
    @include('myfinance2::general.partials.styles')
@endsection
@php
    $periodMap = ['3m' => '3M', '6m' => '6M', '1y' => '1Y', '2y' => '2Y'];
    $severityClass = [
        'HIGH'   => 'bg-danger',
        'MEDIUM' => 'bg-warning text-dark',
        'LOW'    => 'bg-secondary',
    ];
    $actionClass = [
        'ACCUMULATE' => 'bg-success',
        'HOLD'       => 'bg-info text-dark',
        'REDUCE'     => 'bg-warning text-dark',
        'EXIT'       => 'bg-danger',
    ];

    $windowsLabel = function (?string $csv) use ($periodMap) {
        $parts = array_filter(array_map('trim', explode(',', (string) $csv)));
        return collect($parts)->map(fn ($w) => $periodMap[$w] ?? strtoupper($w))->implode(', ');
    };

    // Friendly heading for a dismissal-day group key (a Y-m-d string, or 'unknown').
    $dateLabel = function (string $key) {
        if ($key === 'unknown') {
            return 'Unknown date';
        }
        $date = \Illuminate\Support\Carbon::parse($key);
        if ($date->isToday()) {
            return 'Today, ' . $date->format('M j');
        }
        if ($date->isYesterday()) {
            return 'Yesterday, ' . $date->format('M j');
        }
        return $date->format('D, M j, Y');
    };
@endphp
@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card card-default">
                    <div class="card-header">
                        <div class="float-right d-flex align-items-center gap-2 flex-wrap">
                            @include('myfinance2::peakproximityalerts.partials.subnav', ['active' => 'inbox'])
                            <div class="btn-group btn-group-sm" role="group" aria-label="Filter alerts">
                                <a href="{{ route('myfinance2::peak-proximity-alerts.inbox') }}"
                                   class="btn {{ $show === 'open' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                   data-bs-toggle="tooltip" title="Alerts still open">
                                    Open
                                </a>
                                <a href="{{ route('myfinance2::peak-proximity-alerts.inbox', ['show' => 'dismissed']) }}"
                                   class="btn {{ $show === 'dismissed' ? 'btn-primary' : 'btn-outline-secondary' }}"
                                   data-bs-toggle="tooltip" title="Alerts you have dismissed">
                                    Dismissed
                                </a>
                            </div>
                            <a href="{{ url('/watchlist-symbols') }}"
                               class="btn btn-outline-secondary btn-sm"
                               data-bs-toggle="tooltip"
                               title="Open the watchlist symbols dashboard">
                                <i class="fa fa-fw fa-line-chart" aria-hidden="true"></i> Watchlist
                            </a>
                        </div>
                        Peak-Proximity Inbox
                    </div>
                    <div class="card-body">
                        @include('myfinance2::peakproximityalerts.partials.rules')

                        @if ($show === 'dismissed')
                            <h5 class="mt-3 mb-2">
                                Dismissed
                                <span class="badge bg-secondary ms-1">{{ $dismissedCount }}</span>
                            </h5>
                            @forelse ($dismissed as $day => $events)
                                <div class="d-flex align-items-center gap-2 mt-3 mb-2 pb-1 border-bottom">
                                    <h6 class="mb-0">{{ $dateLabel($day) }}</h6>
                                    <span class="badge bg-light text-dark">{{ $events->count() }}</span>
                                </div>
                                {{-- Two cards per row on wide (xl, >=1200px) screens; one otherwise. --}}
                                <div class="row g-3">
                                    @foreach ($events as $event)
                                        <div class="col-12 col-xl-6">
                                            @include('myfinance2::peakproximityalerts.inbox.partials.card', [
                                                'event'         => $event,
                                                'severityClass' => $severityClass,
                                                'actionClass'   => $actionClass,
                                                'windowsLabel'  => $windowsLabel,
                                            ])
                                        </div>
                                    @endforeach
                                </div>
                            @empty
                                <p class="text-muted small">No dismissed alerts yet.</p>
                            @endforelse
                        @else

                        {{-- Action suggested --}}
                        <div class="d-flex align-items-center justify-content-between mt-3 mb-2">
                            <h5 class="mb-0">
                                Action suggested
                                <span class="badge bg-danger ms-1">{{ $actionable->count() }}</span>
                            </h5>
                            @if ($actionable->isNotEmpty())
                                <form method="POST"
                                      action="{{ route('myfinance2::peak-proximity-alerts.dismiss-all') }}"
                                      onsubmit="return confirm('Dismiss all action-suggested alerts?');">
                                    @csrf
                                    <input type="hidden" name="scope" value="actionable">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                        Dismiss all suggested
                                    </button>
                                </form>
                            @endif
                        </div>

                        {{-- Two cards per row on wide (xl, >=1200px) screens; one otherwise. --}}
                        <div class="row g-3">
                            @forelse ($actionable as $event)
                                <div class="col-12 col-xl-6">
                                    @include('myfinance2::peakproximityalerts.inbox.partials.card', [
                                        'event'         => $event,
                                        'severityClass' => $severityClass,
                                        'actionClass'   => $actionClass,
                                        'windowsLabel'  => $windowsLabel,
                                    ])
                                </div>
                            @empty
                                <div class="col-12">
                                    <p class="text-muted small">
                                        No actionable alerts. A symbol lands here when a weak-tier (Rust or
                                        Bronze) holding is near its 6M, 1Y or 2Y peak.
                                    </p>
                                </div>
                            @endforelse
                        </div>

                        {{-- For your awareness --}}
                        <div class="d-flex align-items-center justify-content-between mt-4 mb-2">
                            <h5 class="mb-0">
                                For your awareness
                                <span class="badge bg-secondary ms-1">{{ $info->count() }}</span>
                            </h5>
                            @if ($info->isNotEmpty())
                                <form method="POST"
                                      action="{{ route('myfinance2::peak-proximity-alerts.dismiss-all') }}"
                                      onsubmit="return confirm('Dismiss all informational alerts?');">
                                    @csrf
                                    <input type="hidden" name="scope" value="info">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                        Dismiss all informational
                                    </button>
                                </form>
                            @endif
                        </div>

                        {{-- Two cards per row on wide (xl, >=1200px) screens; one otherwise. --}}
                        <div class="row g-3">
                            @forelse ($info as $event)
                                <div class="col-12 col-xl-6">
                                    @include('myfinance2::peakproximityalerts.inbox.partials.card', [
                                        'event'         => $event,
                                        'severityClass' => $severityClass,
                                        'actionClass'   => $actionClass,
                                        'windowsLabel'  => $windowsLabel,
                                    ])
                                </div>
                            @empty
                                <div class="col-12">
                                    <p class="text-muted small">
                                        No informational alerts. Strong holdings near a peak (which do not
                                        email) would show up here.
                                    </p>
                                </div>
                            @endforelse
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="clearfix mb-4"></div>
    </div>
@endsection
@section('footer_scripts')
    @include('myfinance2::general.scripts.tooltips')
@endsection
