<div class="table-responsive">
    <table class="table table-sm table-striped data-table dividend-items-table">
        <thead class="thead">
            <tr>
                <th scope="col">Id</th>
                <th scope="col">Timestamp</th>
                <th scope="col" style="min-width: 128px">Account</th>
                <th scope="col">Symbol</th>
                <th scope="col" class="text-right"
                    style="min-width: 108px">Amount</th>
                <th scope="col" class="hidden-xs"
                    style="min-width: 128px">Exchange Rate</th>
                <th scope="col" class="text-right hidden-xs"
                    style="min-width: 74px">Fee</th>
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
                <td>{{ $item->accountModel->name }}
                    ({!! $item->accountModel->currency->display_code !!})</td>
                <td>{{ $item->symbol }}</td>
                <td class="text-right pr-2">
                    <div data-bs-toggle="tooltip"
                        title="Amount in dividend currency">
                        {!! $item->getFormattedAmount() !!}
                    </div>
                    @if($item->accountModel->currency_id !=
                        $item->dividend_currency_id)
                    <div data-bs-toggle="tooltip" title="Amount in account currency">
                        {!! $item->getFormattedAmountInAccountCurrency() !!}
                    </div>
                    @endif
                </td>
                <td class="hidden-xs">
                    {{ @number_format($item->exchange_rate, 4) }}
                </td>
                <td class="text-right pr-2 hidden-xs">
                    {!! $item->getFormattedFee() !!}
                </td>
                <td class="hidden-xs">{{ $item->description }}</td>
                <td class="hidden-xs hidden-sm">{{ $item->created_at }}</td>
                <td class="hidden-xs hidden-sm">{{ $item->updated_at }}</td>
                <td>
                    <a class="btn btn-sm btn-outline-secondary btn-block"
                        href="{{ route('myfinance2::dividends.edit', $item->id) }}"
                        data-bs-toggle="tooltip"
                        title="{{ trans('myfinance2::general.tooltips.edit-item',
                                  ['type' => 'Dividend']) }}">
                        {!! trans('myfinance2::general.buttons.edit') !!}
                    </a>
                </td>
                <td>
                    @include('myfinance2::dividends.forms.delete-sm',
                             ['type' => 'Dividend', 'id' => $item->id])
                </td>
            </tr>
            @endforeach
        @endif
        </tbody>
    </table>
    <div class="clearfix mb-3"></div>
</div>

