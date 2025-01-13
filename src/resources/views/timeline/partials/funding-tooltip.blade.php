<table class="table table-sm">
    <thead>
        <tr>
            <th scope="col">#</th>
            <th scope="col">Debit</th>
            <th scope="col">Credit</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <th scope="row">Id</th>
            <td>{{ $debit_transaction ? $debit_transaction->id : '' }}</td>
            <td>{{ $credit_transaction ? $credit_transaction->id : '' }}</td>
        </tr>
        <tr>
            <th scope="row">Timestamp</th>
            <td>{{ $debit_transaction ? $debit_transaction->timestamp : '' }}</td>
            <td>{{ $credit_transaction ? $credit_transaction->timestamp : '' }}</td>
        </tr>
        <tr>
            <th scope="row">Account</th>
            <td>
                {!! $debit_transaction
                    ? $debit_transaction->debitAccountModel->name
                    . ' ('
                    . $debit_transaction->debitAccountModel->currency->display_code
                    . ')'
                    : $credit_transaction->debitAccountModel->name
                    . ' ('
                    . $credit_transaction->debitAccountModel->currency->display_code
                    . ')'
                !!}
            </td>
            <td>
                {!! $credit_transaction
                    ? $credit_transaction->creditAccountModel->name
                    . ' ('
                    . $credit_transaction->creditAccountModel->currency->display_code
                    . ')'
                    : $debit_transaction->creditAccountModel->name
                    . ' ('
                    . $debit_transaction->creditAccountModel->currency->display_code
                    . ')'
                !!}
            </td>
        </tr>
        <tr>
            <th scope="row">Amount</th>
            <td>
                {!! $debit_transaction
                    ? $debit_transaction->getFormattedAmount()
                    : ''
                !!}
            </td>
            <td>
                {!! $credit_transaction
                    ? $credit_transaction->getFormattedAmount()
                    : ''
                !!}
            </td>
        </tr>
        <tr>
            <th scope="row">Fee</th>
            <td>
                {!! $debit_transaction
                    ? $debit_transaction->getFormattedFee()
                    : ''
                !!}
            </td>
            <td>
                {!! $credit_transaction
                    ? $credit_transaction->getFormattedFee()
                    : ''
                !!}
            </td>
        </tr>
        <tr>
            <th scope="row">Exchange</th>
            <td>
                {!! $debit_transaction
                    ? $debit_transaction->exchange_rate
                    : ''
                !!}
            </td>
            <td>
                {!! $credit_transaction
                    ? $credit_transaction->exchange_rate
                    : ''
                !!}
            </td>
        </tr>
    </tbody>
</table>

