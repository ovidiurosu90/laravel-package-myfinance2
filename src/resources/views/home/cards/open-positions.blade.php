<div class="col-sm-4 mb-3 d-flex">
    <div class="card">
        <div class="card-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span id="card_title">
                    <a target="_blank" href="{{ url('/positions') }}">
                        {!! trans('myfinance2::positions.titles.open-positions') !!}
                    </a>
                </span>
                <div class="float-right">
                    <a class="btn btn-sm" href="{{ route('myfinance2::trades.create') }}" target="_blank" data-bs-toggle="tooltip" title="{{ trans('myfinance2::general.tooltips.create-item', ['type' => 'Trade']) }}">
                        <i class="fa fa-fw fa-plus" aria-hidden="true"></i>
                        {!! trans('myfinance2::general.buttons.create') !!}
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="list-group-flush flex-fill">
                @if(count(array_keys($openPositions)) != 0)
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th scope="col">Account</th>
                            <th scope="col" class="text-right" style="min-width: 106px" data-bs-toggle="tooltip" title="Total Cost in account currency">Total cost</th>
                            <th scope="col" class="text-right" style="min-width: 106px" data-bs-toggle="tooltip" title="Total Current Market Value in account currency">Total market value</th>
                            <th scope="col" class="text-right" style="min-width: 106px" data-bs-toggle="tooltip" title="Total Overall Gain in account currency">Total gain</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($openPositions as $account => $totals)
                        <tr>
                            <th scope="row">{{ $account }}</th>
                            <td class="text-right">{!! $totals['total_cost_formatted'] !!}</td>
                            <td class="text-right">{!! $totals['total_market_value_formatted'] !!}</td>
                            <td class="text-right">{!! $totals['total_change_formatted'] !!}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @else
                <p class="m-3">{{ trans('myfinance2::home.cards.open-positions.no-items') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>

