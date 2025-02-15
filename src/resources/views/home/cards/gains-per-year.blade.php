<div class="col">
    <div class="card">
        <div class="card-header">
            <div style="display: flex; justify-content: space-between;
                        align-items: center;">
                <span id="card_title">
                    {!! trans('myfinance2::home.cards.gains-per-year.title') !!}
                </span>
                <div class="float-right">
                    <a class="btn btn-sm"
                        href="{{ route('myfinance2::trades.create') }}"
                        target="_blank" data-bs-toggle="tooltip"
                        title="{{ trans('myfinance2::general.tooltips.create-item',
                                        ['type' => 'Trade']) }}">
                        <i class="fa fa-fw fa-plus" aria-hidden="true"></i>
                        {!! trans('myfinance2::general.buttons.create') !!}
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body p-0" style="height: 496px; overflow: auto">
            <div class="list-group-flush flex-fill">
                @if(count(array_keys($gainsPerYear)) != 0)
                <table class="table table-striped">
                    <thead style="position: sticky; top: 0; z-index: 100;
                                  background-color: white;">
                        <tr>
                            <th scope="col">Account</th>
                            <th scope="col">Symbol</th>
                            <th scope="col" class="text-right"
                                data-bs-toggle="tooltip"
                                title="Total Gain (after deducting fees)
                                       in account currency">Total gain</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($gainsPerYear as $year => $accounts)
                        @php $yearTotals = [] @endphp
                        @foreach($accounts as $account => $symbols)
                            @foreach($symbols as $symbol => $totals)
                        <tr>
                            <td scope="row">
                                {{ $totals['accountModel']->name }}
                                ({!! $totals['accountModel']->currency
                                                            ->display_code !!})
                            </td>
                            <td scope="row">{{ $symbol }}</td>
                            <td class="text-right">
                                {!! ovidiuro\myfinance2\App\Services\MoneyFormat
                                ::get_formatted_gain(
                                    $totals['accountModel']->currency->display_code,
                                    $totals['total_gain_in_account_currency'])
                                !!}
                            </td>
                        </tr>
                            @if(empty($yearTotals[$totals['accountModel']
                                        ->currency->iso_code]))
                                @php
                                    $yearTotals[$totals['accountModel']
                                        ->currency->iso_code] = 0
                                @endphp
                            @endif
                            @php
                                $yearTotals[$totals['accountModel']
                                    ->currency->iso_code] +=
                                        $totals['total_gain_in_account_currency']
                            @endphp
                            @endforeach
                        @endforeach
                        <tr class="border border-bottom border-success">
                            <th scope="row">{{ $year }}</th>
                            <td colspan="2" class="text-right">
                            @foreach($yearTotals as $currency => $total)
                                &nbsp;&nbsp;&nbsp;
                                {!! ovidiuro\myfinance2\App\Services\MoneyFormat
                                ::get_formatted_gain(
                                    $currencyUtilsService->getCurrencyByIsoCode(
                                        $currency)->display_code,
                                    $total
                                ) !!}
                            @endforeach
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @else
                <p class="m-3">
                    {{ trans('myfinance2::home.cards.gains-per-year.no-items') }}
                </p>
                @endif
            </div>
        </div>
    </div>
</div>

