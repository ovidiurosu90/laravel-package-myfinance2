<div class="table-responsive">
    <table class="table table-sm table-striped data-table order-items-table">
        <thead class="thead">
            <tr role="row">
                <th>Id</th>
                <th>Placed At</th>
                <th>Account</th>
                <th>Status</th>
                <th>Action</th>
                <th>Symbol</th>
                <th>Qty</th>
                <th class="text-right text-nowrap">Limit Price</th>
                <th class="text-right text-nowrap">P Amount</th>
                <th class="d-none d-xl-table-cell text-nowrap">L Trade</th>
                <th class="d-none d-xl-table-cell">Description</th>
                <th class="d-none d-xl-table-cell">Created</th>
                <th class="no-search no-sort">Actions</th>
                <th class="no-search no-sort"></th>
                <th class="no-search no-sort"></th>
            </tr>
        </thead>
        <tbody class="table-body">
        @if ($items->count() > 0)
            @foreach ($items as $item)
            <tr>
                <td>
                    {{ $item->id }}
                    @if ($item->trade_id)
                        <a href="{{ route('myfinance2::trades.edit', $item->trade_id) }}"
                            class="badge bg-secondary text-decoration-none d-block mt-1"
                            data-bs-toggle="tooltip"
                            title="L Trade #{{ $item->trade_id }}">
                            #{{ $item->trade_id }}
                        </a>
                    @endif
                </td>
                <td class="text-nowrap">{{ $item->placed_at ? $item->placed_at->format('Y-m-d H:i:s') : '—' }}</td>
                <td class="text-nowrap">
                    @if ($item->accountModel)
                        {{ $item->accountModel->name }}
                        ({!! $item->accountModel->currency->display_code !!})
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td>
                    <span class="badge {{ $item->getStatusBadgeClass() }}">
                        {{ $item->status }}
                    </span>
                </td>
                <td>{{ $item->action }}</td>
                <td>
                    <a href="{{ route('myfinance2::orders.index', ['view' => 'all',
                                                                   'symbol' => $item->symbol]) }}">
                        {{ $item->symbol }}
                    </a>
                </td>
                <td>{{ $item->getCleanQuantity() ?: '—' }}</td>
                <td class="text-right text-nowrap">
                    {!! $item->getFormattedLimitPrice() !!}
                    @if ($item->exchange_rate && $item->exchange_rate != 1)
                        <div class="text-nowrap text-muted small">
                            FX {{ $item->exchange_rate + 0 }}
                        </div>
                    @endif
                </td>
                <td class="text-right text-nowrap">
                    {!! $item->getFormattedPrincipleAmount() !!}
                </td>
                <td class="d-none d-xl-table-cell">
                    @if ($item->trade_id)
                        <a href="{{ route('myfinance2::trades.edit', $item->trade_id) }}"
                            data-bs-toggle="tooltip"
                            title="L Trade #{{ $item->trade_id }}">
                            #{{ $item->trade_id }}
                        </a>
                        @include('myfinance2::orders.forms.unlink-trade-sm',
                                 ['id' => $item->id])
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td class="d-none d-xl-table-cell">{{ $item->description }}</td>
                <td class="d-none d-xl-table-cell">{{ $item->created_at }}</td>
                <td class="text-nowrap">
                    @if ($item->status === 'DRAFT')
                        @include('myfinance2::orders.forms.place-sm', ['id' => $item->id])
                    @elseif ($item->status === 'PLACED')
                        @include('myfinance2::orders.forms.fill-sm',
                                 ['id' => $item->id, 'trade_id' => $item->trade_id,
                                  'label' => $item->getShortLabel()])
                        @include('myfinance2::orders.forms.expire-sm', ['id' => $item->id])
                    @elseif ($item->status === 'FILLED' && !$item->trade_id)
                        @include('myfinance2::orders.forms.link-trade-sm',
                                 ['id' => $item->id, 'label' => $item->getShortLabel()])
                    @endif
                    @if (!$item->isTerminal())
                        @include('myfinance2::orders.forms.cancel-sm', ['id' => $item->id])
                    @else
                        @include('myfinance2::orders.forms.reopen-sm', ['id' => $item->id])
                    @endif
                </td>
                <td>
                    @if ($item->isEditable())
                        <a class="btn btn-sm btn-outline-secondary w-100"
                            href="{{ route('myfinance2::orders.edit', $item->id) }}"
                            data-bs-toggle="tooltip"
                            title="{{ trans('myfinance2::general.tooltips.edit-item',
                                            ['type' => 'Order']) }}">
                            {!! trans('myfinance2::general.buttons.edit') !!}
                        </a>
                    @endif
                </td>
                <td>
                    @include('myfinance2::orders.forms.delete-sm',
                             ['type' => 'Order', 'id' => $item->id])
                </td>
            </tr>
            @endforeach
        @endif
        </tbody>
        <tfoot class="tfoot">
            <tr role="row">
                <th>Id</th>
                <th>Placed At</th>
                <th>Account</th>
                <th>Status</th>
                <th>Action</th>
                <th>Symbol</th>
                <th>Qty</th>
                <th class="text-right text-nowrap">Limit Price</th>
                <th class="text-right text-nowrap">P Amount</th>
                <th class="d-none d-xl-table-cell text-nowrap">L Trade</th>
                <th class="d-none d-xl-table-cell">Description</th>
                <th class="d-none d-xl-table-cell">Created</th>
                <th class="no-search no-sort">Actions</th>
                <th class="no-search no-sort"></th>
                <th class="no-search no-sort"></th>
            </tr>
        </tfoot>
    </table>
    <div class="clearfix mb-3"></div>
</div>
