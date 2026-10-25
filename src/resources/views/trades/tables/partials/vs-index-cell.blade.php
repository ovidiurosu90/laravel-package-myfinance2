{{-- The trade list's "Vs Index" cell (TradeVsIndex): points against VUSA.AS, positive when the decision was
     right. $comparison: ['points', 'title'] or null. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
<td class="d-none d-xl-table-cell text-right text-nowrap" data-order="{{ $comparison['points'] ?? -999999 }}">
    @if($comparison !== null)
        <span data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide" title="{{ $comparison['title'] }}"
            >{!! MoneyFormat::get_formatted_gain('pts', $comparison['points'], 1) !!}</span>
    @else
        <span class="text-muted">-</span>
    @endif
</td>
