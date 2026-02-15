@use('ovidiuro\myfinance2\App\Services\MoneyFormat')

@if(!empty($virtualFundingAccounts))
<h6 class="text-muted mb-1"
    style="font-size: 0.85rem;">
    <i class="fa-solid fa-vault me-1"
        style="font-size: 0.7rem;"></i>
    Virtual Funding
</h6>
<p class="text-muted mb-1" style="font-size: 0.75rem;">
    Cost basis of transferred positions, shown
    in the Funding card.
</p>
<table class="table table-sm table-striped mb-0">
    <thead>
        <tr>
            <th>Account</th>
            <th class="text-right table-warning">
                &euro;
            </th>
        </tr>
    </thead>
    <tbody>
    @foreach($virtualFundingAccounts as $accountId => $data)
        <tr>
            <td>{{ $data['account']->name }}</td>
            <td class="text-right text-nowrap
                       table-warning">
                {!! MoneyFormat::get_formatted_gain(
                    '&euro;',
                    $data['balance_in_eur']
                ) !!}
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif
