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
            <td class="pr-5 pt-1 fs-5">
                <span id="currency_exchange-status" data-bs-toggle="tooltip"
                    title=""></span>
            </td>
            <td class="pr-2 pt-1 fs-5 fw-bold text-center">
                -<span id="cost-status"></span>
            </td>
            <td class="pr-2 pt-1 fs-5 fw-bold text-center">
                +<span id="mvalue-status"></span>
            </td>
            <td class="pr-5 pt-1 fs-5 fw-bold text-center">
                = <span id="change-status"></span>
            </td>
            <td class="pt-1 fs-5 fw-bold text-center">
                <span id="cash-status"></span>
            </td>
        </tr>
        <tr>
            <td></td>
            <td class="align-top"><span id="currency_exchange-status-time"></span>&nbsp;&nbsp;<span id="eurusd-signal"></span></td>
            <td class="align-top"><span id="cost-status-percentage"></span></td>
            <td><div id="mvalue-range-bar" class="px-1"></div></td>
            <td><div id="change-range-bar" class="px-3"></div></td>
            <td><div id="cash-range-bar" class="px-1"></div></td>
        </tr>
    </table>
    <div id="chart-userOverview" class="mt-3" data-currency_iso_code="{{ $currency }}"></div>
</div>

