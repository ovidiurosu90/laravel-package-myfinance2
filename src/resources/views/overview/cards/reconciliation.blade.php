@use('ovidiuro\myfinance2\App\Services\MoneyFormat')

{{-- How the Funding, Investment and Gains Per Year totals connect (OverviewReconciliation), collapsed by default:
     the header shows growth, what the gains explain and the gap; the body breaks them down. --}}
@php
    $rec = $reconciliation;
    $unrealizedTooltip = implode('<br>', array_map(
        fn ($acc) => e($acc['name']) . ': ' . strip_tags(MoneyFormat::get_formatted_gain($acc['display_code'],
            $acc['gain'])) . ($acc['display_code'] !== '&euro;'
            ? ' (' . strip_tags(MoneyFormat::get_formatted_gain('&euro;', $acc['gain_eur'])) . ')' : ''),
        $rec['unrealized_accounts']
    ));
    $explainedPct = $rec['explained_pct'] !== null
        ? MoneyFormat::get_formatted_pct($rec['explained_pct']) . ' %' : null;
@endphp
<div class="col-12">
    <div class="card">
        <div class="card-header d-flex align-items-center">
            <span id="card_title" class="collapse-title collapsed" data-bs-toggle="collapse"
                data-bs-target="#reconciliation-body" aria-expanded="false" aria-controls="reconciliation-body">
                @include('myfinance2::general.partials.collapse-chevron')
                <i class="fa-solid fa-scale-balanced me-1" style="font-size: 0.8rem; color: #6c757d;"></i>
                {!! trans('myfinance2::overview.cards.reconciliation.title') !!}
            </span>
            <span class="ms-auto small text-muted">
                Growth {!! MoneyFormat::get_formatted_gain('&euro;', $rec['growth']) !!}
                &middot; explained by gains {!! MoneyFormat::get_formatted_gain('&euro;', $rec['explained']) !!}
                @if($explainedPct)
                    ({{ $explainedPct }} of the growth)
                @endif
                &middot; gap {!! MoneyFormat::get_formatted_gain('&euro;', $rec['gap']) !!}
            </span>
        </div>
        <div id="reconciliation-body" class="collapse">
            <div class="card-body py-2 px-3">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr>
                                    <td>Investment today <span class="text-muted">(Investment total)</span></td>
                                    <td class="text-right">
                                        {!! MoneyFormat::get_formatted_balance('&euro;', $rec['investment']) !!}
                                    </td>
                                </tr>
                                <tr>
                                    <td>Money put in <span class="text-muted">(Funding total)</span></td>
                                    <td class="text-right">
                                        {!! MoneyFormat::get_formatted_gain('&euro;', $rec['funding']) !!}
                                    </td>
                                </tr>
                                <tr class="table-warning">
                                    <td><strong>= Total growth</strong></td>
                                    <td class="text-right">
                                        <strong>
                                            {!! MoneyFormat::get_formatted_gain('&euro;', $rec['growth']) !!}
                                        </strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Sold: stock gains <span class="text-muted">(Gains Per Year)</span></td>
                                    <td class="text-right">
                                        {!! MoneyFormat::get_formatted_gain('&euro;', $rec['realized_stock']) !!}
                                    </td>
                                </tr>
                                <tr>
                                    <td>Dividends <span class="text-muted">(Gains Per Year)</span></td>
                                    <td class="text-right">
                                        {!! MoneyFormat::get_formatted_gain('&euro;', $rec['realized_dividends']) !!}
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        Still held: open positions
                                        <span class="text-muted">(Investment Gain column, today&#039;s rate)</span>
                                    </td>
                                    <td class="text-right">
                                        <span @if($unrealizedTooltip !== '') data-bs-toggle="tooltip"
                                            data-bs-html="true" data-bs-custom-class="tooltip-md"
                                            data-bs-title="{{ $unrealizedTooltip }}" @endif>
                                            {!! MoneyFormat::get_formatted_gain('&euro;', $rec['unrealized']) !!}
                                        </span>
                                    </td>
                                </tr>
                                @if(abs($rec['intermediary']) >= 0.01)
                                <tr>
                                    <td>
                                        Not invested yet
                                        <span class="text-muted">(intermediary accounts)</span>
                                    </td>
                                    <td class="text-right">
                                        {!! MoneyFormat::get_formatted_gain('&euro;', -$rec['intermediary']) !!}
                                    </td>
                                </tr>
                                @endif
                                <tr class="table-info">
                                    <td><strong>= Explained by gains</strong></td>
                                    <td class="text-right">
                                        <strong>
                                            {!! MoneyFormat::get_formatted_gain('&euro;', $rec['explained']) !!}
                                        </strong>
                                        @if($explainedPct)
                                            <span class="text-muted small">({{ $explainedPct }} of the growth)</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>Gap</strong> <span class="text-muted">(growth minus explained)</span>
                                    </td>
                                    <td class="text-right">
                                        <strong>{!! MoneyFormat::get_formatted_gain('&euro;', $rec['gap']) !!}</strong>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-6">
                        @include('myfinance2::overview.cards.partials.reconciliation-gap')
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
