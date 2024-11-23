<div class="col-sm-4 mb-3 d-flex">
    <div class="card">
        <div class="card-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span id="card_title">
                    {!! trans('myfinance2::home.cards.gains-per-year.title') !!}
                </span>
            </div>
        </div>
        <div class="card-body p-0" style="height: 330px; overflow: auto">
            <div class="list-group-flush flex-fill">
                @if(count(array_keys($gainsPerYear)) != 0)
                <table class="table table-striped">
                    <thead style="position: sticky; top: 0; z-index: 100; background-color: white;">
                        <tr>
                            <th scope="col">Account</th>
                            <th scope="col">Symbol</th>
                            <th scope="col" class="text-right" style="min-width: 105px" data-bs-toggle="tooltip" title="Total Gain (after deducting fees) in account currency">Total gain</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($gainsPerYear as $year => $accounts)
                        @php $yearTotals = [] @endphp
                        @foreach($accounts as $account => $symbols)
                            @foreach($symbols as $symbol => $totals)
                        <tr>
                            <th scope="row">{{ $account }}</th>
                            <th scope="row">{{ $symbol }}</th>
                            <td class="text-right">{!! ovidiuro\myfinance2\App\Services\MoneyFormat::get_formatted_gain($totals['account_currency'], $totals['total_gain_in_account_currency']) !!}</td>
                        </tr>
                            @if(empty($yearTotals[$totals['account_currency']]))
                            @php $yearTotals[$totals['account_currency']] = 0 @endphp
                            @endif
                            @php $yearTotals[$totals['account_currency']] +=  $totals['total_gain_in_account_currency'] @endphp
                            @endforeach
                        @endforeach
                        <tr class="border border-bottom border-success">
                            <th scope="row">{{ $year }}</th>
                            <th scope="row"></th>
                            <td class="text-right">
                            @foreach($yearTotals as $currency => $total)
                                {!! ovidiuro\myfinance2\App\Services\MoneyFormat::get_formatted_gain($currency, $total) !!}
                            @endforeach
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @else
                <p class="m-3">{{ trans('myfinance2::home.cards.gains-per-year.no-items') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>

