<div class="table-responsive">
    <table class="table table-sm table-striped data-table trade-items-table">
        <thead class="thead">
            <tr>
                <th scope="col">Id</th>
                <th scope="col">Timestamp</th>
                <th scope="col" style="min-width: 122px">Account</th>
                <th scope="col">Status</th>
                <th scope="col">Action</th>
                <th scope="col">Symbol</th>
                <th scope="col">Quantity</th>
                <th scope="col" class="text-right"
                    style="min-width: 106px">Unit Price</th>
                <th scope="col" class="text-right" style="min-width: 106px"
                    data-bs-toggle="tooltip"
                    title="Quantity * Unit Price">Principle Amount</th>
                <th scope="col" class="hidden-xs">Exchange Rate</th>
                <th scope="col" class="text-right hidden-xs"
                    style="min-width: 88px">Fee</th>
                <th scope="col" class="hidden-xs"
                    style="min-width: 180px">Description</th>
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
                <td>{{ $item->timestamp }}</td>
                <td>{{ $item->accountModel->name }}
                    ({!! $item->accountModel->currency->display_code !!})</td>
                <td>{{ $item->status }}</td>
                <td>{{ $item->action }}</td>
                <td>{!! $item->getFormattedSymbol() !!}</td>
                <td>{{ $item->getCleanQuantity() }}</td>
                <td class="text-right">{!! $item->getFormattedUnitPrice() !!}</td>
                <td class="text-right">
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
                <td class="hidden-xs">
                    {{ $item->getCleanExchangeRate() }}
                </td>
                <td class="text-right hidden-xs">
                    {!! $item->getFormattedFee() !!}
                </td>
                <td class="hidden-xs">{{ $item->description }}</td>
                <td class="hidden-xs hidden-sm">{{ $item->created_at }}</td>
                <td class="hidden-xs hidden-sm">{{ $item->updated_at }}</td>
                <td>
                @if($item->status != 'CLOSED')
                    @include('myfinance2::trades.forms.close-sm',
                             ['id' => $item->id])
                @endif
                </td>
                <td>
                    <a class="btn btn-sm btn-outline-secondary btn-block"
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
            <tr>
                <th scope="col">Id</th>
                <th scope="col">Timestamp</th>
                <th scope="col">Account</th>
                <th scope="col">Status</th>
                <th scope="col">Action</th>
                <th scope="col">Symbol</th>
                <th scope="col">Quantity</th>
                <th scope="col">Unit Price</th>
                <th scope="col" data-bs-toggle="tooltip"
                    title="Quantity * Unit Price">Principle Amount</th>
                <th scope="col" class="hidden-xs">Exchange Rate</th>
                <th scope="col" class="hidden-xs">Fee</th>
                <th scope="col" class="hidden-xs">Description</th>
                <th scope="col" class="hidden-xs hidden-sm">Created</th>
                <th scope="col" class="hidden-xs hidden-sm">Updated</th>
                <th class="no-search no-sort"></th>
                <th class="no-search no-sort"></th>
                <th class="no-search no-sort"></th>
            </tr>
        </tfoot>
    </table>
    <div class="clearfix mb-3"></div>
</div>

