@use('ovidiuro\myfinance2\App\Services\MoneyFormat')

<div class="col-8 ps-1">
    <div class="card">
        <div class="card-header">
            <span id="card_title">
                {!! trans('myfinance2::overview.cards.'
                          . 'investment-accounts.title') !!}
            </span>
        </div>
        <div class="card-body p-0" style="height: 320px; overflow: auto">
            <div class="list-group-flush flex-fill">
                @if(count($investmentAccountsWithPositions) > 0)
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th scope="col">Account</th>
                            <th scope="col" class="text-right"
                                data-bs-toggle="tooltip"
                                title="Total Cost in account currency">
                                Cost
                            </th>
                            <th scope="col"
                                class="text-right text-nowrap
                                       table-info"
                                data-bs-toggle="tooltip"
                                title="Total Current Market Value
                                        in account currency">
                                MValue
                            </th>
                            <th scope="col" class="text-right"
                                data-bs-toggle="tooltip"
                                title="Total Overall Gain
                                        in account currency">
                                Gain
                            </th>
                            <th scope="col"
                                class="text-right table-info"
                                data-bs-toggle="tooltip"
                                title="Cash balance
                                        in account currency">
                                Cash
                            </th>
                            <th scope="col"
                                class="text-right"
                                data-bs-toggle="tooltip"
                                title="MValue + Cash
                                        in account currency">
                                Balance
                            </th>
                            <th scope="col"
                                class="text-right table-warning"
                                data-bs-toggle="tooltip"
                                title="Balance converted to EUR">
                                Balance &euro;
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($investmentAccountsWithPositions as $accountId => $totals)
                        @php
                            $tooltip = '';
                            if (!empty($totals['conversion_pair'])) {
                                $tooltip = 'EURUSD: '
                                    . number_format(
                                        $totals['eurusd_rate'], 4
                                    )
                                    . '<br>'
                                    . $totals['conversion_pair']
                                    . ': '
                                    . number_format(
                                        $totals['exchange_rate'], 4
                                    );
                            }
                        @endphp
                        <tr>
                            <td>
                                {{ $totals['accountModel']->name }}
                                ({!! $totals['accountModel']->currency
                                                            ->display_code
                                !!})
                            </td>
                            <td class="text-right text-nowrap">
                                {!! $totals['total_cost_formatted'] !!}
                            </td>
                            <td class="text-right text-nowrap table-info">
                                {!! $totals['total_market_value_formatted']
                                !!}
                            </td>
                            <td class="text-right text-nowrap">
                                {!! $totals['total_change_formatted'] !!}
                            </td>
                            <td class="text-right text-nowrap table-info">
                                {!! $totals['cash_formatted'] !!}
                            </td>
                            <td class="text-right text-nowrap">
                                {!! MoneyFormat::get_formatted_gain(
                                    $totals['accountModel']->currency
                                                           ->display_code,
                                    $totals['balance']
                                ) !!}
                            </td>
                            <td class="text-right text-nowrap
                                       table-warning">
                                <span
                                    @if($tooltip)
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        data-bs-custom-class="big-tooltips"
                                        data-bs-html="true"
                                        data-bs-title="{!! $tooltip !!}"
                                    @endif>
                                    {!! MoneyFormat::get_formatted_gain(
                                        '&euro;',
                                        $totals['balance_in_eur']
                                    ) !!}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="table-warning">
                            <td><strong>Total</strong></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td class="text-right text-nowrap">
                                <strong>
                                    {!! MoneyFormat::get_formatted_gain(
                                        '&euro;',
                                        collect(
                                            $investmentAccountsWithPositions
                                        )->sum('balance_in_eur')
                                    ) !!}
                                </strong>
                            </td>
                        </tr>
                    </tfoot>
                </table>
                @else
                <p class="m-3">
                    {{ trans('myfinance2::overview.cards.'
                             . 'investment-accounts.no-items') }}
                </p>
                @endif
            </div>
        </div>
    </div>
</div>
