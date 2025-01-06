<div class="col-sm-4 mb-3 d-flex">
    <div class="card">
        <div class="card-header">
            <div style="display: flex; justify-content: space-between;
                        align-items: center;">
                <span id="card_title">
                    <a target="_blank" href="{{ url('/dividends') }}">
                        {!! trans('myfinance2::dividends.titles.dashboard') !!}
                    </a>
                </span>
                <div class="float-right">
                    <a class="btn btn-sm"
                        href="{{ route('myfinance2::dividends.create') }}"
                        target="_blank" data-bs-toggle="tooltip"
                        title="{{ trans('myfinance2::general.tooltips.create-item',
                                        ['type' => 'Dividend']) }}">
                        <i class="fa fa-fw fa-plus" aria-hidden="true"></i>
                        {!! trans('myfinance2::general.buttons.create') !!}
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body p-0" style="height: 330px; overflow: auto">
            <div class="list-group-flush flex-fill">
                @if(count(array_keys($dividends)) != 0)
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th scope="col">Account</th>
                            <th scope="col">Symbol</th>
                            <th scope="col" class="text-right"
                                style="min-width: 105px" data-bs-toggle="tooltip"
                                title="Total Gain (after deducting fees)
                                        in account currency">Total gain</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($dividends as $accountId => $symbols)
                        @foreach($symbols as $symbol => $totals)
                        <tr>
                            <th scope="row">
                                {{ $totals['accountModel']->name }}
                                ({!! $totals['accountModel']->currency->display_code
                                !!})
                            </th>
                            <th scope="row">{{ $symbol }}</th>
                            <td class="text-right">
                                {!! ovidiuro\myfinance2\App\Services\MoneyFormat
                                ::get_formatted_gain($totals['accountModel']
                                    ->currency->iso_code,
                                $totals['total_gain_in_account_currency']) !!}</td>
                        </tr>
                        @endforeach
                    @endforeach
                    </tbody>
                </table>
                @else
                <p class="m-3">
                    {{ trans('myfinance2::home.cards.dividends.no-items') }}
                </p>
                @endif
            </div>
        </div>
    </div>
</div>

