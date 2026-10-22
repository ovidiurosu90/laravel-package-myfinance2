{{-- Stock split applied email: the split, every trade it rewrote (quantity and price, old and new) and every price
     alert target it adjusted. --}}
@extends('myfinance2::emails.layouts.base')
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')

@section('styles')
        .section { border-left-color: #fd7e14; }
        .arrow { color: #6c757d; }
        .new-val { font-weight: 600; }
        h3 { font-size: 15px; margin: 20px 0 4px; }
        .none-msg { color: #6c757d; font-style: italic; margin: 0; }
@endsection

@section('title')
    ✂️ Stock Split Applied: {{ $split->symbol }} {{ $split->getRatioLabel() }}
@endsection

@section('body')
    {{-- Split details --}}
    <div class="section">
        <h2>Split event</h2>
        <table>
            <tr>
                <th>Symbol</th>
                <th>Split Date</th>
                <th>Ratio</th>
                @if ($split->notes)
                <th>Notes</th>
                @endif
            </tr>
            <tr>
                <td>
                    <a class="sym-link" href="https://finance.yahoo.com/quote/{{ $split->symbol }}" target="_blank">
                        <strong>{{ $split->symbol }}</strong>
                    </a>
                </td>
                <td>{{ $split->split_date->format('Y-m-d') }}</td>
                <td><strong>{{ $split->getRatioLabel() }}</strong></td>
                @if ($split->notes)
                <td>{{ $split->notes }}</td>
                @endif
            </tr>
        </table>
    </div>

    {{-- Changed trades --}}
    <h3>Trades Updated ({{ $summary['trades_updated'] }})</h3>
    @if (!empty($summary['changed_trades']))
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Account</th>
                <th>Date</th>
                <th>Action</th>
                <th class="num">Old Qty</th>
                <th class="num"></th>
                <th class="num">New Qty</th>
                <th class="num">Old Price</th>
                <th class="num"></th>
                <th class="num">New Price</th>
                <th>Currency</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($summary['changed_trades'] as $t)
            <tr>
                <td>{{ $t['id'] }}</td>
                <td>{{ $t['account'] }}</td>
                <td class="text-nowrap">{{ $t['date'] }}</td>
                <td>{{ strtolower($t['action']) }}</td>
                <td class="num">{{ MoneyFormat::get_formatted_quantity_plain($t['old_quantity']) }}</td>
                <td class="num arrow">&rarr;</td>
                <td class="num new-val">{{ MoneyFormat::get_formatted_quantity_plain($t['new_quantity']) }}</td>
                <td class="num">{{ MoneyFormat::get_formatted_price($t['old_price'], true) }}</td>
                <td class="num arrow">&rarr;</td>
                <td class="num new-val">{{ MoneyFormat::get_formatted_price($t['new_price'], true) }}</td>
                <td>{{ $t['currency'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p class="none-msg">
        No trades were found for {{ $split->symbol }} on or before {{ $split->split_date->format('Y-m-d') }}.
    </p>
    @endif

    {{-- Adjusted alerts --}}
    <h3>Alerts Adjusted ({{ $summary['alerts_adjusted'] }})</h3>
    @if (!empty($summary['changed_alerts']))
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Type</th>
                <th class="num">Old Target</th>
                <th class="num"></th>
                <th class="num">New Target</th>
                <th>Currency</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($summary['changed_alerts'] as $a)
            @php
                $typeLabel = $a['alert_type'] === 'PRICE_ABOVE' ? '&#9650; Above' : '&#9660; Below';
                $typeClass = $a['alert_type'] === 'PRICE_ABOVE' ? 'bg-danger' : 'bg-primary';
            @endphp
            <tr>
                <td>{{ $a['id'] }}</td>
                <td><span class="badge {{ $typeClass }}">{!! $typeLabel !!}</span></td>
                <td class="num">{{ MoneyFormat::get_formatted_price($a['old_target_price'], true) }}</td>
                <td class="num arrow">&rarr;</td>
                <td class="num new-val">{{ MoneyFormat::get_formatted_price($a['new_target_price'], true) }}</td>
                <td>{{ $a['currency'] ?? '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p class="none-msg">No active price alerts were found for {{ $split->symbol }}.</p>
    @endif

    @include('myfinance2::emails.partials.action-links', ['links' => [
        ['label' => '→ View All Splits', 'url' => $dashboardUrl],
    ]])
@endsection

@section('footer-title', 'Stock splits')
