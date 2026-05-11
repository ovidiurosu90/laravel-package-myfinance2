@php $currency = !empty(app('request')->input('currency_iso_code'))
    ? app('request')->input('currency_iso_code') : 'EUR' @endphp

<div class="position-relative">
    <table>
        <tr>
            <td class="pr-3">
                <input id="toggle-currency-select" type="checkbox"
                    {{ $currency == 'EUR' ? 'checked' : '' }}
                    data-bs-toggle="toggle"
                    data-onlabel="Euro (&euro;)" data-offlabel="US Dollar (&dollar;)" />
            </td>
            <td class="pt-1 fs-5 px-2">
                <span id="currency_exchange-status"></span>&nbsp;<span id="eurusd-signal"></span>
            </td>
            <td class="pr-2 pt-1 fs-5 fw-bold text-center">
                -<span id="cost-status"></span>
            </td>
            <td class="pr-2 pt-1 fs-5 fw-bold text-center">
                +<span id="mvalue-status"></span>
            </td>
            <td class="pt-1 fs-5 fw-bold text-center">=</td>
            <td class="pr-5 pt-1 fs-5 fw-bold text-center">
                <span id="change-status"></span>
            </td>
            <td class="pt-1 fs-5 fw-bold text-center">
                <span id="cash-status"></span>
            </td>
        </tr>
        <tr>
            <td class="pr-3 pt-1 align-bottom">
                <div class="btn-group w-100" role="group"
                    style="font-size:0.6rem;line-height:1.2;">
                    <button type="button" class="btn btn-outline-secondary zoom-btn flex-fill py-0 px-0" data-days="7">1W</button>
                    <button type="button" class="btn btn-outline-secondary zoom-btn flex-fill py-0 px-0" data-days="30">1M</button>
                    <button type="button" class="btn btn-outline-secondary zoom-btn active flex-fill py-0 px-0" data-days="365">1Y</button>
                    <button type="button" class="btn btn-outline-secondary zoom-btn flex-fill py-0 px-0" data-days="0">ALL</button>
                </div>
            </td>
            <td class="px-2"><div id="eurusd-range-bar"></div></td>
            <td class="pr-2"><div id="cost-range-bar"></div></td>
            <td class="pr-2"><div id="mvalue-range-bar"></div></td>
            <td></td>
            <td class="pr-5"><div id="change-range-bar"></div></td>
            <td><div id="cash-range-bar"></div></td>
        </tr>
    </table>
    <div id="chart-userOverview" class="mt-3" data-currency_iso_code="{{ $currency }}"></div>
</div>

