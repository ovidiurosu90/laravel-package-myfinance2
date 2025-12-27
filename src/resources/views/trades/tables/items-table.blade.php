<style>
    @media (max-width: 1199.98px) {
        .trade-items-table th:nth-child(2),
        .trade-items-table td:nth-child(2) {
            width: 20%;
            min-width: 0;
        }
    }
</style>
<div class="table-responsive">
    <table class="table table-sm table-striped data-table trade-items-table">
        <thead class="thead">
            <tr role="row">
                <th>Id</th>
                <th>Timestamp</th>
                <th>Account</th>
                <th>Status</th>
                <th>Action</th>
                <th>Symbol</th>
                <th>Quantity</th>
                <th class="text-right text-nowrap">Unit Price</th>
                <th class="text-right text-nowrap"
                    data-bs-toggle="tooltip"
                    title="Principle Amount = Quantity * Unit Price">P Amount</th>
                <th class="text-right text-nowrap">Fee</th>
                <th class="d-none d-xl-table-cell">Description</th>
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
                <td>{{ $item->timestamp }}</td>
                <td class="text-nowrap">{{ $item->accountModel->name }}
                    ({!! $item->accountModel->currency->display_code !!})</td>
                <td>{{ $item->status }}</td>
                <td>{{ $item->action }}</td>
                <td>{!! $item->getFormattedSymbol() !!}</td>
                <td>{{ $item->getCleanQuantity() }}</td>
                <td class="text-right">
                    <div class="text-nowrap">{!! $item->getFormattedUnitPrice() !!}</div>
                    <div class="text-nowrap">FX {{ $item->getCleanExchangeRate() }}</div>
                </td>
                <td class="text-right text-nowrap">
                    <div data-bs-toggle="tooltip" title="Amount in trade currency">
                        {!! $item->getFormattedPrincipleAmount() !!}
                    </div>
                    @if($item->accountModel->currency->iso_code !=
                        $item->tradeCurrencyModel->iso_code)
                    <div data-bs-toggle="tooltip"
                        title="Amount in account currency">
                        {!! $item->getFormattedPrincipleAmountInAccountCurrency()
                        !!}
                    </div>
                    @endif
                </td>
                <td class="text-right text-nowrap">
                    {!! $item->getFormattedFee() !!}
                </td>
                <td class="d-none d-xl-table-cell">{{ $item->description }}</td>
                <td class="d-none d-xl-table-cell">{{ $item->created_at }}</td>
                <td class="d-none d-xl-table-cell">{{ $item->updated_at }}</td>
                <td>
                @if($item->status != 'CLOSED')
                    @include('myfinance2::trades.forms.close-sm',
                             ['id' => $item->id])
                @endif
                </td>
                <td>
                    <a class="btn btn-sm btn-outline-secondary w-100"
                        href="{{ route('myfinance2::trades.edit', $item->id) }}"
                        data-bs-toggle="tooltip"
                        title="{{ trans('myfinance2::general.tooltips.edit-item',
                                        ['type' => 'Trade']) }}">
                        {!! trans('myfinance2::general.buttons.edit') !!}
                    </a>
                </td>
                <td>
                    @include('myfinance2::trades.forms.delete-sm',
                             ['type' => 'Trade', 'id' => $item->id])
                </td>
            </tr>
            @endforeach
        @endif
        </tbody>
        <tfoot class="tfoot">
            <tr role="row">
                <th>Id</th>
                <th>Timestamp</th>
                <th>Account</th>
                <th>Status</th>
                <th>Action</th>
                <th>Symbol</th>
                <th>Quantity</th>
                <th class="text-right text-nowrap">Unit Price</th>
                <th class="text-right text-nowrap"
                    data-bs-toggle="tooltip"
                    title="Principle Amount = Quantity * Unit Price">P Amount</th>
                <th class="text-right text-nowrap">Fee</th>
                <th class="d-none d-xl-table-cell">Description</th>
                <th class="d-none d-xl-table-cell">Created</th>
                <th class="d-none d-xl-table-cell">Updated</th>
                <th class="no-search no-sort">Actions</th>
                <th class="no-search no-sort"></th>
                <th class="no-search no-sort"></th>
            </tr>
        </tfoot>
    </table>
    <div class="clearfix mb-3"></div>
</div>

