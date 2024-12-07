<div class="table-responsive">
    <table class="table table-sm table-striped data-table transaction-items-table">
        {{-- NOTE! Pagination already has this information
        <caption class="p-1 pb-0">
            {!! trans_choice('myfinance2::ledger.transactions-table.caption', $items->count(), ['count' => $items->count()]) !!}
        </caption>
        --}}
        <thead class="thead">
            <tr>
                <th scope="col">Id</th>
                <th scope="col">Parent Id</th>
                <th scope="col">Timestamp</th>
                <th scope="col">Type</th>
                <th scope="col" style="min-width: 108px">Debit Account</th>
                <th scope="col" style="min-width: 108px">Credit Account</th>
                <th scope="col" class="text-right" style="min-width: 96px">Amount</th>
                <th scope="col" class="hidden-xs">Exchange Rate</th>
                <th scope="col" class="text-right hidden-xs" style="min-width: 74px">Fee</th>
                <th scope="col" class="hidden-xs">Description</th>
                <th scope="col" class="hidden-xs hidden-sm">Created</th>
                <th scope="col" class="hidden-xs hidden-sm">Updated</th>
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
                        <td>{{ $item->getDebitAccount() }}</td>
                        <td>{{ $item->getCreditAccount() }}</td>
                        <td class="text-right">{!! $item->getFormattedAmount() !!}</td>
                        <td class="hidden-xs">{{ @number_format($item->exchange_rate, 4) }}</td>
                        <td class="text-right hidden-xs">{!! $item->getFormattedFee() !!}</td>
                        <td class="hidden-xs">{{ $item->description }}</td>
                        <td class="hidden-xs hidden-sm">{{ $item->created_at }}</td>
                        <td class="hidden-xs hidden-sm">{{ $item->updated_at }}</td>
                        <td>
                            <a class="btn btn-sm btn-outline-success btn-block" href="{{ route('myfinance2::ledger-transactions.create', ['parent_id' => $item->id]) }}" data-bs-toggle="tooltip" title="{{ trans('myfinance2::ledger.tooltips.create-child-transaction') }}">
                                {!! trans('myfinance2::ledger.buttons.create-child-transaction') !!}
                            </a>
                        </td>
                        {{--
                        <td>
                            <a class="btn btn-sm btn-outline-info btn-block" href="{{ route('myfinance2::ledger-transactions.show', $item->id) }}" data-bs-toggle="tooltip" title="{{ trans('myfinance2::general.tooltips.show-item', ['type' => 'Ledger Transaction']) }}">
                                {!! trans('myfinance2::general.buttons.show') !!}
                            </a>
                        </td>
                        --}}
                        <td>
                            <a class="btn btn-sm btn-outline-secondary btn-block" href="{{ route('myfinance2::ledger-transactions.edit', $item->id) }}" data-bs-toggle="tooltip" title="{{ trans('myfinance2::general.tooltips.edit-item', ['type' => 'Ledger Transaction']) }}">
                                {!! trans('myfinance2::general.buttons.edit') !!}
                            </a>
                        </td>
                        <td>
                            @include('myfinance2::ledger.forms.delete-sm', ['type' => 'Transaction', 'id' => $item->id])
                        </td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
    <div class="clearfix mb-3"></div>
</div>

