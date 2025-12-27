<style>
    @media (max-width: 1199.98px) {
        .transaction-items-table th:nth-child(10),
        .transaction-items-table td:nth-child(10) {
            width: 30%;
            min-width: 0;
        }
    }
</style>
<div class="table-responsive">
    <table class="table table-sm table-striped data-table transaction-items-table">
        <thead class="thead">
            <tr role="row">
                <th>Id</th>
                <th class="text-nowrap" title="Parent Id">P Id</th>
                <th>Timestamp</th>
                <th>Type</th>
                <th class="text-nowrap">Debit Acc</th>
                <th class="text-nowrap">Credit Acc</th>
                <th class="text-right">Amount</th>
                <th class="text-nowrap text-right">FX Rate</th>
                <th class="text-right">Fee</th>
                <th>Description</th>
                <th class="d-none d-xl-table-cell">Created</th>
                <th class="d-none d-xl-table-cell">Updated</th>
                <th class="no-search no-sort">Actions</th>
                <th class="no-search no-sort"></th>
                <th class="no-search no-sort"></th>
            </tr>
        </thead>
        <tbody class="table-body">
        @if( $items->count() > 0)
            @foreach($items as $item)
            <tr>
                <td>{{ $item->id }}</td>
                <td>{{ $item->parent_id }}</td>
                <td>{{ $item->timestamp }}</td>
                <td>{{ $item->type }}</td>
                <td>
                    {{ $item->debitAccountModel->name }}
                    ({!! $item->debitAccountModel->currency->display_code !!})
                </td>
                <td>
                    {{ $item->creditAccountModel->name }}
                    ({!! $item->creditAccountModel->currency->display_code !!})
                </td>
                <td class="text-nowrap text-right">{!! $item->getFormattedAmount() !!}</td>
                <td class="text-nowrap text-right">
                    {{ $item->getCleanExchangeRate() }}
                </td>
                <td class="text-nowrap text-right">
                    {!! $item->getFormattedFee() !!}
                </td>
                <td>{{ $item->description }}</td>
                <td class="d-none d-xl-table-cell">{{ $item->created_at }}</td>
                <td class="d-none d-xl-table-cell">{{ $item->updated_at }}</td>
                <td>
                    <a class="btn btn-sm btn-outline-success w-100"
                        href="{{ route('myfinance2::ledger-transactions.create',
                                       ['parent_id' => $item->id]) }}"
                        data-bs-toggle="tooltip"
                        title="{{ trans('myfinance2::ledger.tooltips.'
                                        . 'create-child-transaction') }}">
                        {!! trans('myfinance2::ledger.buttons.'
                                  . 'create-child-transaction') !!}
                    </a>
                </td>
                <td>
                    <a class="btn btn-sm btn-outline-secondary w-100"
                        href="{{ route('myfinance2::ledger-transactions.edit',
                                       $item->id) }}"
                        data-bs-toggle="tooltip"
                        title="{{ trans('myfinance2::general.tooltips.edit-item',
                                        ['type' => 'Ledger Transaction']) }}">
                        {!! trans('myfinance2::general.buttons.edit') !!}
                    </a>
                </td>
                <td>
                    @include('myfinance2::ledger.forms.delete-sm',
                        ['type' => 'Transaction', 'id' => $item->id])
                </td>
            </tr>
            @endforeach
        @endif
        </tbody>
    </table>
    <div class="clearfix mb-3"></div>
</div>

