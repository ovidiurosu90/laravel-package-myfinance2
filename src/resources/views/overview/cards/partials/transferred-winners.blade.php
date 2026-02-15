@use('ovidiuro\myfinance2\App\Services\MoneyFormat')

@if(!empty($transferredAccounts) || !empty($transferredSymbols))
<h6 class="text-muted mb-1"
    style="font-size: 0.85rem;">
    <i class="fa-solid fa-trophy me-1"
        style="font-size: 0.7rem;"></i>
    Affected Top Winners
</h6>
<table class="table table-sm table-striped mb-0">
    <thead>
        <tr>
            <th>Account / Symbol</th>
            <th class="text-right table-warning">
                &euro;
            </th>
        </tr>
    </thead>
    <tbody>
    @foreach($transferredAccounts as $acc)
        <tr>
            <td>{{ $acc['name'] }}</td>
            <td class="text-right text-nowrap
                       table-warning">
                {!! MoneyFormat::get_formatted_gain(
                    '&euro;',
                    $acc['transferred_eur']
                ) !!}
            </td>
        </tr>
    @endforeach
    @foreach($transferredSymbols as $sym)
        <tr>
            <td>{{ $sym['name'] }}</td>
            <td class="text-right text-nowrap
                       table-warning">
                {!! MoneyFormat::get_formatted_gain(
                    '&euro;',
                    $sym['transferred_eur']
                ) !!}
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif
