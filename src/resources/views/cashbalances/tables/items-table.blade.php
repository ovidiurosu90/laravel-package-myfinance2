<div class="table-responsive">
    <table class="table table-sm table-striped data-table cash-balance-items-table">
        <thead class="thead">
            <tr>
                <th scope="col">Id</th>
                <th scope="col">Timestamp</th>
                <th scope="col" style="min-width: 118px">Account</th>
                <th scope="col" class="text-right" style="min-width: 120px">Amount</th>
                <th scope="col" class="hidden-xs">Description</th>
                <th scope="col" class="hidden-xs hidden-sm">Created</th>
                <th scope="col" class="hidden-xs hidden-sm">Updated</th>
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
                        <td>{{ $item->getAccount() }}</td>
                        <td class="text-right pr-2">
                            <div data-bs-toggle="tooltip" title="Amount in account currency">{!! $item->getFormattedAmount() !!}</div>
                        </td>
                        <td class="hidden-xs">{{ $item->description }}</td>
                        <td class="hidden-xs hidden-sm">{{ $item->created_at }}</td>
                        <td class="hidden-xs hidden-sm">{{ $item->updated_at }}</td>
                        <td>
                            <a class="btn btn-sm btn-outline-secondary btn-block" href="{{ route('myfinance2::cash-balances.edit', $item->id) }}" data-bs-toggle="tooltip" title="{{ trans('myfinance2::general.tooltips.edit-item', ['type' => 'Cash Balance']) }}">
                                {!! trans('myfinance2::general.buttons.edit') !!}
                            </a>
                        </td>
                        <td>
                            @include('myfinance2::cashbalances.forms.delete-sm', ['type' => 'Cash Balance', 'id' => $item->id])
                        </td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
</div>

