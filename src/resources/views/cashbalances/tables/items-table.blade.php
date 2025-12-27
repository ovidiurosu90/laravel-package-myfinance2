<div class="table-responsive">
    <table class="table table-sm table-striped data-table cash-balance-items-table">
        <thead class="thead">
            <tr role="row">
                <th>Id</th>
                <th>Timestamp</th>
                <th>Account</th>
                <th class="text-right">Amount</th>
                <th>Description</th>
                <th>Created</th>
                <th>Updated</th>
                <th class="no-search no-sort">Actions</th>
                <th class="no-search no-sort"></th>
            </tr>
        </thead>
        <tbody class="table-body">
        @if( $items->count() > 0)
            @foreach($items as $item)
            <tr>
                <td>{{ $item->id }}</td>
                <td>{{ $item->timestamp }}</td>
                <td class="text-nowrap">{{ $item->accountModel->name }}
                    ({!! $item->accountModel->currency->display_code !!})</td>
                <td class="text-right text-nowrap pr-2">
                    <div data-bs-toggle="tooltip"
                        title="Amount in account currency">
                        {!! $item->getFormattedAmount() !!}
                    </div>
                </td>
                <td>{{ $item->description }}</td>
                <td>{{ $item->created_at }}</td>
                <td>{{ $item->updated_at }}</td>
                <td>
                    <a class="btn btn-sm btn-outline-secondary w-100"
                        href="{{ route('myfinance2::cash-balances.edit',
                                       $item->id) }}"
                        data-bs-toggle="tooltip"
                            title="{{ trans('myfinance2::general.tooltips.edit-item',
                                            ['type' => 'Cash Balance']) }}">
                            {!! trans('myfinance2::general.buttons.edit') !!}
                        </a>
                    </td>
                    <td>
                        @include('myfinance2::cashbalances.forms.delete-sm',
                                 ['type' => 'Cash Balance', 'id' => $item->id])
                    </td>
                </tr>
                @endforeach
            @endif
        </tbody>
    </table>
    <div class="clearfix mb-3"></div>
</div>

