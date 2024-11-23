<table class="table table-sm">
    <tbody>
        <tr>
            <th scope="row">Id</th>
            <td>{{ $trade->id }}</td>
        </tr>
        <tr>
            <th scope="row">Timestamp</th>
            <td>{{ $trade->timestamp->format(trans('myfinance2::general.datetime-format')) }}</td>
        </tr>
        <tr>
            <th scope="row">Account</th>
            <td>{{ $trade->getAccount() }}</td>
        </tr>
        <tr>
            <th scope="row">Action</th>
            <td>{{ $trade->action }}</td>
        </tr>
        <tr>
            <th scope="row">Symbol</th>
            <td>{{ $trade->symbol }}</td>
        </tr>
        <tr>
            <th scope="row">Quantity</th>
            <td>{{ $trade->quantity }}</td>
        </tr>
        <tr>
            <th scope="row">Unit Price</th>
            <td>{!! $trade->getFormattedUnitPrice() !!}</td>
        </tr>
        <tr>
            <th scope="row">Principle Amount</th>
            <td>{!! $trade->getFormattedPrincipleAmount() !!}</td>
        </tr>
        <tr>
            <th scope="row">Exchange</th>
            <td>{{ $trade->exchange_rate }}</td>
        </tr>
        <tr>
            <th scope="row">Fee</th>
            <td>{!! $trade->getFormattedFee() !!}</td>
        </tr>
    </tbody>
</table>

