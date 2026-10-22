{{-- Returns missing quotes email: positions the returns refresh valued at 0 for lack of a price, per year, and how
     to fix them. --}}
@extends('myfinance2::emails.layouts.base')
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')

@section('styles')
        .section { border-left-color: #dc3545; }
        code { background: #e9ecef; padding: 1px 4px; border-radius: 3px; font-size: 12px; }
@endsection

@section('title', '⚠️ Returns: positions valued at 0 (no price found)')

@section('body')
    <div class="section">
        The returns refresh found no price for the positions below, on the valuation date or up to 7 days
        before it, so they are left out of the start/end value and the return of those years is wrong.
        This usually means the symbol was delisted and the finance API dropped its history.
    </div>

    <table>
        <thead>
            <tr>
                <th>Year</th>
                <th>Valuation date</th>
                <th>Symbol</th>
                <th>Account</th>
                <th class="num">Quantity</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($positionsByYear as $year => $positions)
                @foreach ($positions as $position)
                <tr>
                    <td>{{ $year }}</td>
                    <td class="text-nowrap">{{ $position['date'] }}</td>
                    <td>
                        <a class="sym-link" href="https://finance.yahoo.com/quote/{{ $position['symbol'] }}"
                           target="_blank"><strong>{{ $position['symbol'] }}</strong></a>
                        @if ($position['delisted'])
                            <span class="badge bg-secondary">Delisted</span>
                        @endif
                    </td>
                    <td>{{ $position['account_name'] }}</td>
                    <td class="num">{{ MoneyFormat::get_formatted_quantity_plain($position['quantity']) }}</td>
                </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <p style="margin-top: 16px;">
        Fix: add a <code>price_overrides</code> entry for the last trading day on or before each valuation
        date (Jan 1 uses the previous Dec 31) and, if the symbol was delisted, add it to
        <code>delisted_symbols</code>. Then refresh the returns cache.
    </p>

    @include('myfinance2::emails.partials.action-links', ['links' => array_map(
        fn ($year, $url) => ['label' => "→ Returns {$year}", 'url' => $url],
        array_keys($returnsLinks), array_values($returnsLinks)
    )])
@endsection

@section('footer-title', 'Returns refresh (app:finance-api-cron --refresh-returns)')
