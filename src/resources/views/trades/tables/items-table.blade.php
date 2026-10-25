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
                <th class="text-nowrap text-start">
                    Tier <i class="fas fa-info-circle fa-xs text-muted" aria-hidden="true" data-bs-toggle="tooltip"
                        data-bs-custom-class="tooltip-wide"
                        title="At the decision, locked: the order's reading when the trade filled an order (the order
                            number under the trade id), else the trade's own (live when recorded the same day, rebuilt
                            from the stored history for a past date). The verdict (no warning, soft warnings, against
                            the tiers), the tier in use with its chips and the 7 approaches beneath."></i>
                </th>
                <th class="text-right text-nowrap">
                    <span data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide"
                          title="Principal amount = quantity × unit price, in the trade currency (and the account
                              currency when it differs); quantity × unit price and the exchange rate beneath.
                              Sorts by the amount in EUR at today's rate, signed as cash: a BUY negative, a SELL
                              positive.">Amount</span>
                </th>
                <th class="text-right text-nowrap">Fee</th>
                <th class="d-none d-xl-table-cell text-right text-nowrap no-search">
                    <span data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide"
                        title="The trade against VUSA.AS since it was made, in points: a BUY from its price to today's
                            (or to the SELL that closed it), less VUSA.AS over the same span; a SELL the other way
                            round, so positive means the sale was right. In the symbol's own currency.">Vs Index</span>
                </th>
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
                <td>
                    {{ $item->id }}
                    @foreach ($item->linkedOrders as $order)
                        <a href="{{ route('myfinance2::orders.edit', $order->id) }}"
                            class="badge bg-secondary text-decoration-none d-block mt-1"
                            data-bs-toggle="tooltip"
                            title="Order #{{ $order->id }}: {{ $order->action }} {{ $order->symbol }} ({{ $order->status }})">
                            #{{ $order->id }}
                        </a>
                    @endforeach
                </td>
                <td>{{ $item->timestamp }}</td>
                <td class="text-nowrap">{{ $item->accountModel->name }}
                    ({!! $item->accountModel->currency->display_code !!})</td>
                <td>{{ $item->status }}</td>
                <td>
                    {{ $item->action }}
                    @if($item->is_transfer)
                        <i class="fa-solid fa-shuffle text-muted"
                            data-bs-toggle="tooltip"
                            title="In-kind transfer"></i>
                    @endif
                </td>
                <td>{!! $item->getFormattedSymbol() !!}</td>
                @include('myfinance2::trades.tables.partials.tier-cell', ['trade' => $item])
                @include('myfinance2::trades.tables.partials.amount-cell', ['trade' => $item])
                <td class="text-right text-nowrap">
                    {!! $item->getFormattedFee() !!}
                </td>
                @include('myfinance2::trades.tables.partials.vs-index-cell', [
                    'comparison' => $vsIndex[$item->id] ?? null,
                ])
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
                <th>Tier</th>
                <th class="text-right text-nowrap">
                    <span data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide"
                          title="Principal amount = quantity × unit price, in the trade currency (and the account
                              currency when it differs); quantity × unit price and the exchange rate beneath.
                              Sorts by the amount.">Amount</span>
                </th>
                <th class="text-right text-nowrap">Fee</th>
                <th class="d-none d-xl-table-cell text-right text-nowrap no-search"></th>
                <th class="d-none d-xl-table-cell">Description</th>
                <th class="d-none d-xl-table-cell">Created</th>
                <th class="d-none d-xl-table-cell">Updated</th>
                <th class="no-search no-sort"></th>
                <th class="no-search no-sort"></th>
                <th class="no-search no-sort"></th>
            </tr>
        </tfoot>
    </table>
    <div class="clearfix mb-3"></div>
</div>

