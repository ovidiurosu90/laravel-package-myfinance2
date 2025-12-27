<div class="table-responsive">
    <table class="table table-sm table-striped data-table
                  funding-dashboard-items-table">
        <thead class="thead">
            <tr role="row">
                <th colspan="5" class="text-center table-danger bordered-right">
                    Debit
                </th>
                <th colspan="5" class="text-center table-success bordered-right">
                    Credit
                </th>
                @if(count($balances))
                <th colspan="{{ count($balances) }}"
                    class="text-center table-secondary">
                    Balances
                </th>
                @endif
            </tr>
            <tr role="row">
                <th class="pl-2">Date</th>
                <th>Account</th>
                <th class="text-right">Amount</th>
                <th class="pl-2">Exchange</th>
                <th class="text-right bordered-right">Fee</th>
                <th class="pl-2">Date</th>
                <th>Account</th>
                <th class="text-right">Amount</th>
                <th class="pl-2">Exchange</th>
                <th class="text-right bordered-right">Fee</th>
                @foreach($balances as $accountId => $balance)
                <th class="text-right pr-2">
                    {{ $accounts[$accountId]->name }}
                    ({!! $accounts[$accountId]->currency->display_code !!})
                </th>
                @endforeach
            </tr>
        </thead>
        <tbody class="table-body">
        @if( count($items) > 0)
            @foreach($items as $item)
            <tr>
                <td class="pl-2">
                @if($item['debit_transaction'])
                    <a class="btn btn-sm btn-outline-secondary w-100"
                        href="{{ route('myfinance2::ledger-transactions.edit',
                                       $item['debit_transaction']->id) }}"
                        data-bs-toggle="tooltip"
                        title="{{ trans('myfinance2::general.tooltips.edit-item',
                                        ['type' => 'Ledger Transaction']) }}"
                        target="_blank">
                    {{ $item['debit_transaction']->timestamp->format(
                        trans('myfinance2::general.date-format')) }}
                    </a>
                @endif
                @if($item['debit_transaction']
                    && $item['debit_transaction']->parent_id)
                    <a class="mt-2 btn btn-sm btn-outline-warning w-100"
                        href="{{ route('myfinance2::ledger-transactions.edit',
                                       $item['debit_transaction']->parent_id) }}"
                        data-bs-toggle="tooltip"
                        title="{{ trans('myfinance2::general.tooltips.edit-item',
                                        ['type' => 'Parent Ledger Transaction']) }}"
                        target="_blank">
                        Has Parent
                    </a>
                @endif
                </td>
                <td>
                    @if($item['tooltip'])
                        <i class="btn p-0 m-0 fa fa-exclamation"
                            data-bs-toggle="tooltip" title="{{ $item['tooltip'] }}"
                            style="font-size: 24px;"></i>
                    @endif
                    {!! $item['debit_transaction']
                        ? $item['debit_transaction']->debitAccountModel->name
                        . ' (' . $item['debit_transaction']->debitAccountModel
                            ->currency->display_code . ')'
                        : '<span class="text-warning">'
                        . $item['credit_transaction']->debitAccountModel->name
                        . ' (' . $item['credit_transaction']->debitAccountModel
                             ->currency->display_code . ')'
                        . '<span>' !!}
                </td>
                <td class="text-right text-nowrap">
                    {!! $item['debit_transaction']
                        ? $item['debit_transaction']->getFormattedAmount()
                        : '' !!}
                </td>
                <td class="pl-2 text-right text-nowrap">
                    {{ $item['debit_transaction']
                        ? $item['debit_transaction']->exchange_rate
                        : '' }}
                </td>
                <td class="text-right text-nowrap bordered-right">
                    {!! $item['debit_transaction']
                        ? $item['debit_transaction']->getFormattedFee()
                        : '' !!}</td>
                <td class="pl-2">
                @if($item['credit_transaction'])
                    <a class="btn btn-sm btn-outline-secondary w-100"
                        href="{{ route('myfinance2::ledger-transactions.edit',
                                       $item['credit_transaction']->id) }}"
                        data-bs-toggle="tooltip"
                        title="{{ trans('myfinance2::general.tooltips.edit-item',
                                        ['type' => 'Ledger Transaction']) }}"
                        target="_blank">
                        {{ $item['credit_transaction']->timestamp->format(
                            trans('myfinance2::general.date-format')) }}
                    </a>
                @endif
                <td>
                    {!! $item['credit_transaction']
                        ? $item['credit_transaction']->creditAccountModel->name
                        . ' (' . $item['credit_transaction']->creditAccountModel
                            ->currency->display_code . ')'
                        : '<span class="text-warning">'
                        . $item['debit_transaction']->creditAccountModel->name
                        . ' (' . $item['debit_transaction']->creditAccountModel
                             ->currency->display_code . ')'
                        . '<span>' !!}
                </td>
                <td class="text-right text-nowrap">
                    {!! $item['credit_transaction']
                        ? $item['credit_transaction']->getFormattedAmount()
                        : '' !!}
                </td>
                <td class="pl-2 text-right text-nowrap">
                    {{ $item['credit_transaction']
                        ? $item['credit_transaction']->exchange_rate
                        : '' }}
                </td>
                <td class="text-right text-nowrap bordered-right">
                    {!! $item['credit_transaction']
                        ? $item['credit_transaction']->getFormattedFee()
                        : '' !!}
                </td>
                @foreach($balances as $accountId => $balance)
                <td class="text-right text-nowrap pr-2">
                    {!! ovidiuro\myfinance2\App\Services\MoneyFormat
                        ::get_formatted_gain(
                            $accounts[$accountId]->currency->display_code,
                            $item['balances'][$accountId]) !!}
                </td>
                @endforeach
            </tr>
            @endforeach
        @endif
        </tbody>
        <tfoot class="tfoot">
            <tr role="row">
                <th class="pl-2">Date</th>
                <th>Account</th>
                <th class="text-right">Amount</th>
                <th class="pl-2">Exchange</th>
                <th class="text-right bordered-right">Fee</th>
                <th class="pl-2">Date</th>
                <th>Account</th>
                <th class="text-right">Amount</th>
                <th class="pl-2">Exchange</th>
                <th class="text-right bordered-right">Fee</th>
                @foreach($balances as $accountId => $balance)
                <th class="text-right pr-2">
                    {{ $accounts[$accountId]->name }}
                    ({!! $accounts[$accountId]->currency->display_code !!})
                </th>
                @endforeach
            </tr>
            <tr role="row">
                <th colspan="5" class="text-center table-danger bordered-right">
                    Debit
                </th>
                <th colspan="5" class="text-center table-success bordered-right">
                    Credit
                </th>
                @if(count($balances))
                <th colspan="{{ count($balances) }}"
                    class="text-center table-secondary">
                    Balances
                </th>
                @endif
            </tr>
        </thead>
    </table>
    <div class="clearfix mb-3"></div>
</div>

