<table class="table table-sm">
    <tbody>
        <tr>
            <th scope="row">Id</th>
            <td>{{ $dividend->id }}</td>
        </tr>
        <tr>
            <th scope="row">Timestamp</th>
            <td>{{ $dividend->timestamp->format(trans('myfinance2::general.datetime-format')) }}</td>
        </tr>
        <tr>
            <th scope="row">Account</th>
            <td>{{ $dividend->accountModel->name }}
                ({!! $dividend->accountModel->currency->display_code !!})
            </td>
        </tr>
        <tr>
            <th scope="row">Symbol</th>
            <td>{{ $dividend->symbol }}</td>
        </tr>
        <tr>
            <th scope="row">Amount</th>
            <td>
                {!! $dividend->getFormattedAmount() !!}
                @if($dividend->account_currency != $dividend->dividend_currency)
                <br />{!! $dividend->getFormattedAmountInAccountCurrency() !!}
                @endif
            </td>
        </tr>
        <tr>
            <th scope="row">Exchange</th>
            <td>{{ $dividend->exchange_rate }}</td>
        </tr>
        <tr>
            <th scope="row">Fee</th>
            <td>{!! $dividend->getFormattedFee() !!}</td>
        </tr>
    </tbody>
</table>

