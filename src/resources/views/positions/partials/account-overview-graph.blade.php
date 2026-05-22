@inject('ChartsBuilder', 'ovidiuro\myfinance2\App\Services\ChartsBuilder')

<table style="width:100%">
        <tr>
            <td class="pr-3"></td>
            <td class="pr-2 pt-1 fs-5 fw-bold text-center">
                -<span id="cost-status-{{ $accountId }}"></span>
            </td>
            <td class="pr-2 pt-1 fs-5 fw-bold text-center">
                +<span id="mvalue-status-{{ $accountId }}"></span>
            </td>
            <td class="pt-1 fs-5 fw-bold text-center">=</td>
            <td class="pr-5 pt-1 fs-5 fw-bold text-center">
                <span id="change-status-{{ $accountId }}"></span>
            </td>
            <td class="pt-1 fs-5 fw-bold text-center">
                <span id="cash-status-{{ $accountId }}"></span>
            </td>
        </tr>
        <tr>
            <td class="pr-3 pt-1 align-bottom">
                <div class="btn-group w-100" role="group"
                    style="font-size:0.6rem;line-height:1.2;">
                    <button type="button"
                        class="btn btn-outline-secondary zoom-btn-account flex-fill py-0 px-0"
                        data-account_id="{{ $accountId }}" data-days="30">1M</button>
                    <button type="button"
                        class="btn btn-outline-secondary zoom-btn-account flex-fill py-0 px-0"
                        data-account_id="{{ $accountId }}" data-days="182">6M</button>
                    <button type="button"
                        class="btn btn-outline-secondary zoom-btn-account active flex-fill py-0 px-0"
                        data-account_id="{{ $accountId }}" data-days="365">1Y</button>
                    <button type="button"
                        class="btn btn-outline-secondary zoom-btn-account flex-fill py-0 px-0"
                        data-account_id="{{ $accountId }}" data-days="0">ALL</button>
                </div>
            </td>
            <td class="pr-2"><div id="cost-range-bar-{{ $accountId }}"></div></td>
            <td class="pr-2"><div id="mvalue-range-bar-{{ $accountId }}"></div></td>
            <td></td>
            <td class="pr-5"><div id="change-range-bar-{{ $accountId }}"></div></td>
            <td><div id="cash-range-bar-{{ $accountId }}"></div></td>
        </tr>
    </table>
    <div class="position-relative" style="margin-top:36px;">
        <div class="chart-accountOverview"
            data-account_id="{{ $accountId }}"
            data-account_currency_iso_code="{{ $currency }}"></div>
        <div style="position:absolute;top:-16px;left:0;right:0;z-index:10;
            display:flex;justify-content:space-between;align-items:flex-start;
            pointer-events:none;">
            @foreach($ChartsBuilder::getAccountMetrics() as $metric => $properties)
            @if($metric === 'changePercentage')
            <span id="legend-{{ $metric }}-{{ $accountId }}"
                style="cursor:pointer;pointer-events:auto;
                    border:2px {{ $properties['border_style'] }} {{ $properties['line_color'] }};
                    color:{{ $properties['line_color'] }};border-radius:4px;
                    padding:2px 8px;font-size:0.75rem;user-select:none;
                    background:rgba(255,255,255,0.8);">
                {{ $properties['title'] }}
            </span>
            @endif
            @endforeach
            <div id="legend-right-badges-{{ $accountId }}" style="display:flex;gap:6px;pointer-events:none;">
                @foreach($ChartsBuilder::getAccountMetrics() as $metric => $properties)
                @if($metric !== 'changePercentage')
                <span id="legend-{{ $metric }}-{{ $accountId }}"
                    style="cursor:pointer;pointer-events:auto;
                        border:2px {{ $properties['border_style'] }} {{ $properties['line_color'] }};
                        color:{{ $properties['line_color'] }};border-radius:4px;
                        padding:2px 8px;font-size:0.75rem;user-select:none;
                        background:rgba(255,255,255,0.8);">
                    {{ $properties['title'] }}
                </span>
                @endif
                @endforeach
            </div>
        </div>
    </div>
