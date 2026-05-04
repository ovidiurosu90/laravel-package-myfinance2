{{-- Returns Overview Section
     Displays a chart with returns data across all years.
     Has its own currency toggle independent of the year-specific controls below.
--}}

@php
    $overviewCurrency = !empty(app('request')->input('overview_currency'))
        ? app('request')->input('overview_currency')
        : 'EUR';
@endphp

<div class="card mb-4">
    <div class="card-header">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span id="card_title" style="cursor: pointer;"
                data-bs-toggle="collapse" data-bs-target="#returns-overview-body">
                {{ trans('myfinance2::returns.titles.returns-overview') }}
                <i class="fa fa-chevron-down ms-2" id="returns-overview-chevron"></i>
            </span>
            <div class="d-flex align-items-center gap-2">
                <span style="display: inline-block;"
                    data-bs-toggle="tooltip"
                    data-bs-placement="bottom"
                    data-bs-html="true"
                    data-bs-title="{{ trans('myfinance2::returns.tooltips.toggle-dw') }}">
                    <input type="checkbox"
                        {{ !($excludeDepositsWithdrawals ?? false) ? 'checked' : '' }}
                        disabled
                        data-bs-toggle="toggle"
                        data-onlabel="D&amp;W: On"
                        data-offlabel="D&amp;W: Off"
                        data-onstyle="success"
                        data-offstyle="secondary" />
                </span>
                <span style="display: inline-block;"
                    data-bs-toggle="tooltip"
                    data-bs-placement="bottom"
                    data-bs-html="true"
                    data-bs-title="{{ trans('myfinance2::returns.tooltips.toggle-cash') }}">
                    <input type="checkbox"
                        {{ !($excludeCash ?? false) ? 'checked' : '' }}
                        disabled
                        data-bs-toggle="toggle"
                        data-onlabel="Cash: On"
                        data-offlabel="Cash: Off"
                        data-onstyle="success"
                        data-offstyle="secondary" />
                </span>
                <input id="toggle-overview-currency" type="checkbox"
                    {{ $overviewCurrency === 'EUR' ? 'checked' : '' }}
                    data-bs-toggle="toggle"
                    data-onlabel="Euro (&euro;)"
                    data-offlabel="US Dollar (&dollar;)" />
                <span class="fw-bold fs-5" id="overview-cumulative-total"></span>
            </div>
        </div>
    </div>
    <div id="returns-overview-body" class="collapse show">
        <div class="card-body">
            <div class="position-relative">
                {{-- Axis labels --}}
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span class="text-muted" style="font-size: 0.75rem; margin-left: 54px;">Totals</span>
                    <span class="text-muted" style="font-size: 0.75rem; margin-right: 35px;">Accounts</span>
                </div>
                {{-- Chart container --}}
                <div id="chart-returns-overview"
                    data-overview_currency="{{ $overviewCurrency }}"
                    style="width: 100%; height: 300px;"></div>
                {{-- Legend for accounts --}}
                <div id="overview-legend" class="mt-2" style="font-size: 0.85rem;"></div>
            </div>
        </div>
    </div>
</div>

