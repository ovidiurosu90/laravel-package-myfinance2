@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@use('ovidiuro\myfinance2\App\Services\TierVerdictPresenter')
<table class="table table-sm table-striped data-table order-items-table">
        <thead class="thead">
            <tr role="row">
                <th>Id</th>
                <th>Status</th>
                <th>Symbol</th>
                <th class="text-nowrap text-start">
                    Tier <i class="fas fa-info-circle fa-xs text-muted" aria-hidden="true" data-bs-toggle="tooltip"
                        data-bs-custom-class="tooltip-wide"
                        title="At creation, locked: the tier reading saved when the order was created. The verdict (no
                            warning, soft warnings, against the tiers), the tier in use with its chips and the 7
                            approaches beneath. An open order shows today's tier as well when it differs."></i>
                </th>
                <th>Account</th>
                <th>Action</th>
                <th class="text-right text-nowrap no-search">
                    Limit <i class="fas fa-info-circle fa-xs text-muted" aria-hidden="true" data-bs-toggle="tooltip"
                        title="The limit price; beneath, today's price and how far the limit is from it. Sorts by that
                            gap."></i>
                </th>
                <th class="text-right text-nowrap">
                    <span data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide"
                          title="Principal amount: the total estimated value of the order (quantity × limit price). For
                              a SELL, the projected gain at the limit price beneath, against your average cost. Sorts
                              by the amount.">Amount</span>
                </th>
                <th class="d-none d-xl-table-cell text-nowrap">
                    <span data-bs-toggle="tooltip" title="Linked trade for this order">Trade</span>
                </th>
                <th class="d-none d-xl-table-cell">Description</th>
                <th class="d-none show-1350">Created</th>
                <th class="no-search no-sort">Actions</th>
                <th class="no-search no-sort"></th>
                <th class="no-search no-sort"></th>
            </tr>
        </thead>
        <tbody class="table-body">
        @if ($items->count() > 0)
            @foreach ($items as $item)
            @php
                $qd           = $quoteData[$item->symbol] ?? null;
                $cp           = $qd['price'] ?? null;
                $currencyCode = $item->tradeCurrencyModel ? $item->tradeCurrencyModel->display_code : '';
                $gain         = $projectedGains[$item->id] ?? null;
                $tierSnap     = $item->tierSnapshot;
                $tierToday    = $tierNow[$item->id] ?? null;
                $tierTodayTier = $tierToday['tier'] ?? null;
                $tierNowChip  = $tierSnap !== null && $tierTodayTier !== null
                    && $tierTodayTier !== $tierSnap->effective_tier
                    ? view('myfinance2::orders.partials.tier-now-chip', [
                        'tier' => $tierTodayTier, 'serious' => $tierToday['serious'],
                    ])->render()
                    : '';
            @endphp
            <tr>
                <td>{{ $item->id }}</td>
                <td data-order="{{ $item->placed_at ? $item->placed_at->timestamp : 0 }}">
                    <span class="badge {{ $item->getStatusBadgeClass() }}">
                        {{ $item->status }}
                    </span>
                    @if ($item->placed_at)
                        <div class="text-muted small text-nowrap">
                            {{ $item->placed_at->format('Y-m-d H:i') }}
                        </div>
                    @endif
                </td>
                <td>
                    <a href="https://finance.yahoo.com/quote/{{ $item->symbol }}"
                        target="_blank">
                        {{ $item->symbol }}
                    </a>
                    @if ($item->getCleanQuantity())
                        <div class="text-muted small">x {{ $item->getCleanQuantity() }}</div>
                    @endif
                </td>
                <td class="text-start"
                    data-order="{{ $tierSnap !== null ? TierVerdictPresenter::sortValue($tierSnap) : -1 }}">
                    @include('myfinance2::watchlistsymbols.tables.partials.tier-reading-compact', [
                        'snapshot'   => $tierSnap,
                        'verdictTip' => $tierSnap !== null ? TierVerdictPresenter::tooltip(
                            $tierSnap, $tierTodayTier, $tierToday['serious'] ?? []
                        ) : null,
                        'tierExtra'  => $tierNowChip,
                        'tierNote'   => null,
                    ])
                </td>
                <td class="text-nowrap">
                    @if ($item->accountModel)
                        {{ $item->accountModel->name }}
                        ({!! $item->accountModel->currency->display_code !!})
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td>
                    {{ $item->action }}
                    @if (!empty($activeAlerts[$item->symbol]))
                        @php $limitPrice = $item->limit_price !== null ? (float) $item->limit_price : null; @endphp
                        @foreach ($activeAlerts[$item->symbol] as $activeAlert)
                        @php
                            // Only the order's own companion alert: a RELATIVE target moves and can
                            // equal the limit price by chance.
                            $alertTiedToOrder = $limitPrice !== null
                                && $activeAlert->source === 'order'
                                && !$activeAlert->isRelative()
                                && abs((float) $activeAlert->target_price - $limitPrice) < 0.0001;
                            $alertPrice = MoneyFormat::get_formatted_price_display(
                                $activeAlert->tradeCurrencyModel?->display_code ?? $currencyCode,
                                (float) $activeAlert->target_price,
                                true
                            );

                            $alertTip = ['<strong>Edit alert</strong>', 'Symbol: ' . e($activeAlert->symbol)];
                            if ($activeAlert->getExpiryTooltip()) {
                                $alertTip[] = e($activeAlert->getExpiryTooltip());
                            }
                            if ($alertTiedToOrder) {
                                $alertTip[] = 'Linked to this order';
                            }
                            // A relative badge shows only the short label, so the tooltip carries the price.
                            $alertTip = array_merge($alertTip, $activeAlert->getRelativeBadgeTooltipLines($alertPrice));
                        @endphp
                        <a href="{{ route('myfinance2::price-alerts.edit', $activeAlert->id) }}"
                            class="d-block mt-1"
                            data-bs-toggle="tooltip"
                            data-bs-html="true"
                            title="{!! implode('<br>', $alertTip) !!}">
                            <span class="badge d-block w-100 text-center
                                {{ $activeAlert->getAlertTypeBadgeClass() }}">
                                {{ $activeAlert->alert_type === 'PRICE_ABOVE' ? '▲ Above' : '▼ Below' }}@if ($alertTiedToOrder) <i class="fa fa-link fa-xs" aria-hidden="true"></i>@endif @if ($activeAlert->expires_at)<i class="fa fa-clock-o fa-xs" aria-hidden="true"></i>@endif
                                <br>@include('myfinance2::partials.alert-badge-target', [
                                    'alert'     => $activeAlert,
                                    'priceHtml' => $alertPrice,
                                ])
                            </span>
                        </a>
                        @endforeach
                    @endif
                </td>
                <td class="text-right text-nowrap"
                    data-order="{{ $cp !== null && $item->limit_price
                        ? (float) $item->limit_price - $cp
                        : -999999 }}">
                    @if ($cp !== null && $item->limit_price && $currencyCode)
                        @php
                            $delta     = (float) $item->limit_price - $cp;
                            $deltaPct  = $cp > 0 ? ($delta / $cp) * 100 : 0;
                            $deltaSign = $delta >= 0 ? '+' : '−';
                        @endphp
                        {{-- The limit price, then today's price and how far the limit is from it (stacked, so
                             the column stays narrow; the gap in money in the % tooltip). The two parts of the second
                             line wrap onto two lines when the column is tight. --}}
                        <div class="text-nowrap">{!! $item->getFormattedLimitPrice() !!}</div>
                        <div class="text-muted small text-wrap">
                            <span class="d-inline-block text-nowrap">now
                            @include('myfinance2::partials.price-tooltip', [
                                'currency'           => $currencyCode,
                                'price'              => $qd['price'] ?? null,
                                'timestamp'          => $qd['quote_timestamp'] ?? null,
                                'isPreMarket'        => !empty($qd['pre_market_price']),
                                'isPostMarket'       => !empty($qd['post_market_price']),
                                'dayChange'          => $qd['day_change'] ?? null,
                                'dayChangePct'       => $qd['day_change_pct'] ?? null,
                                'regularPrice'       => $qd['regular_market_price'] ?? null,
                                'regularTimestamp'   => $qd['regular_market_timestamp'] ?? null,
                                'regularDayChange'   => $qd['regular_market_day_change'] ?? null,
                                'regularDayChangePct'=> $qd['regular_market_day_change_pct'] ?? null,
                                'postChange'         => $qd['post_market_change'] ?? null,
                                'postChangePct'      => $qd['post_market_change_pct'] ?? null,
                                'marketOpen'         => $qd['market_open'] ?? false,
                                'content'            => MoneyFormat::get_formatted_price_display($currencyCode, $cp, true),
                            ])</span>
                            <span class="d-inline-block text-nowrap" data-bs-toggle="tooltip"
                                title="Limit vs now: {{ $deltaSign }}{{ html_entity_decode(strip_tags(
                                    MoneyFormat::get_formatted_price_display($currencyCode, abs($delta))
                                ), ENT_QUOTES | ENT_HTML5, 'UTF-8') }}"
                                >{{ $deltaSign }}{{ MoneyFormat::get_formatted_pct(abs($deltaPct)) }}%</span>
                        </div>
                    @else
                        {!! $item->getFormattedLimitPrice() !!}
                    @endif
                    @if ($item->exchange_rate && $item->exchange_rate != 1)
                        <div class="text-nowrap text-muted small">
                            FX {{ $item->exchange_rate + 0 }}
                        </div>
                    @endif
                </td>
                @include('myfinance2::orders.tables.partials.amount-cell', [
                    'order' => $item, 'gain' => $gain, 'currencyCode' => $currencyCode,
                ])
                <td class="d-none d-xl-table-cell">
                    @if ($item->trade_id)
                        <a href="{{ route('myfinance2::trades.edit', $item->trade_id) }}"
                            data-bs-toggle="tooltip"
                            title="Trade #{{ $item->trade_id }}">
                            #{{ $item->trade_id }}
                        </a>
                        @include('myfinance2::orders.forms.unlink-trade-sm',
                                 ['id' => $item->id])
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td class="d-none d-xl-table-cell">{{ $item->description }}</td>
                <td class="d-none show-1350">{{ $item->created_at }}</td>
                <td class="text-nowrap">
                    @if ($item->status === 'DRAFT')
                        @include('myfinance2::orders.forms.place-sm', ['id' => $item->id])
                    @elseif ($item->status === 'PLACED')
                        @include('myfinance2::orders.forms.fill-sm',
                                 ['id' => $item->id, 'trade_id' => $item->trade_id,
                                  'label' => $item->getShortLabel()])
                        @include('myfinance2::orders.forms.expire-sm', ['id' => $item->id])
                        @include('myfinance2::orders.forms.expire-and-clone-sm', ['id' => $item->id])
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
                <th>Status</th>
                <th>Symbol</th>
                <th>Tier</th>
                <th>Account</th>
                <th>Action</th>
                <th class="text-right text-nowrap no-search"></th>
                <th class="text-right text-nowrap">Amount</th>
                <th class="d-none d-xl-table-cell text-nowrap">Trade</th>
                <th class="d-none d-xl-table-cell">Description</th>
                <th class="d-none show-1350">Created</th>
                <th class="no-search no-sort"></th>
                <th class="no-search no-sort"></th>
                <th class="no-search no-sort"></th>
            </tr>
        </tfoot>
    </table>
<div class="clearfix mb-3"></div>
