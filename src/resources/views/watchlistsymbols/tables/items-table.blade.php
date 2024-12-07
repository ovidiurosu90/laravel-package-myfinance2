<div class="table-responsive">
    <table class="table table-sm data-table watchlist-symbol-items-table table-hover">
        <thead class="thead">
            <tr>
                <th scope="col" style="min-width: 98px">Symbol</th>
                <th scope="col">Name</th>
                <th scope="col" class="text-right" style="min-width: 98px">Price</th>
                <th scope="col" class="text-right">Day change</th>
                <th scope="col" class="text-right" style="min-width: 88px">Day change %</th>
                <th scope="col" class="text-right" style="min-width: 98px">52-Wk low</th>
                <th scope="col" class="text-right" style="min-width: 80px">% Above low</th>
                <th scope="col" class="text-right" style="min-width: 98px">52-Wk high</th>
                <th scope="col" class="text-right" style="min-width: 80px">% Below high</th>
                <!--
                <th scope="col" class="text-right hidden-xs">Id</th>
                <th scope="col" class="hidden-xs">Timestamp</th>
                <th scope="col" class="hidden-xs">Description</th>
                <th scope="col" class="hidden-xs hidden-sm">Created</th>
                <th scope="col" class="hidden-xs hidden-sm">Updated</th>
                 -->
                <th class="no-search no-sort" style="min-width: 240px">Open Positions</th>
                <th class="no-search no-sort">Actions</th>
                <th class="no-search no-sort"></th>
            </tr>
        </thead>
        <tbody class="table-body">
            @if( count($items) > 0)
                @foreach($items as $symbol => $quoteData)
                    <tr {!! count($quoteData['open_positions']) > 0 ? 'class="table-info"' : '' !!}>
                        <td>
                            {{ $symbol }}
                            @if( count($quoteData['open_positions']) > 0 )
                                <i class="fa fa-briefcase" aria-hidden="true" data-bs-toggle="tooltip" title="Has Open Positions"></i>
                            @endif
                        </td>
                        <td>
                            <div data-bs-toggle="tooltip" data-bs-custom-class="big-tooltips" data-bs-html="true" title="<p class='text-left'>
Id: {{ $quoteData['item']->id }}<br />
Timestamp: {{ $quoteData['item']->timestamp }}<br />
Description: {{ $quoteData['item']->description }}<br />
Created: {{ $quoteData['item']->created_at }}<br />
Updated: {{ $quoteData['item']->updated_at }}</p>">{!! $quoteData['name'] ? $quoteData['name'] : $symbol !!}
                            </div>
                        </td>
                        <td class="text-right">
                            <div data-bs-toggle="tooltip" data-bs-custom-class="big-tooltips" title="Quote timestamp: {{ $quoteData['quote_timestamp']->format(trans('myfinance2::general.datetime-format')) }}">
                                {!! ovidiuro\myfinance2\App\Services\MoneyFormat::get_formatted_balance($quoteData['currency'], $quoteData['price']) !!}
                            </div>
                        </td>
                        <td class="text-right">{!! ovidiuro\myfinance2\App\Services\MoneyFormat::get_formatted_balance($quoteData['currency'], $quoteData['day_change']) !!}</td>
                        <td class="text-right" data-order="{{ $quoteData['day_change_percentage'] }}">{!! ovidiuro\myfinance2\App\Services\MoneyFormat::get_formatted_balance_percentage($quoteData['day_change_percentage']) !!}</td>
                        <td class="text-right">{!! ovidiuro\myfinance2\App\Services\MoneyFormat::get_formatted_balance($quoteData['currency'], $quoteData['fiftyTwoWeekLow']) !!}</td>
                        <td class="text-right" data-order="{{ $quoteData['fiftyTwoWeekLowChangePercent'] * 100 }}">{!! ovidiuro\myfinance2\App\Services\MoneyFormat::get_formatted_52wk_low_percentage($quoteData['fiftyTwoWeekLowChangePercent'] * 100) !!}</td>
                        <td class="text-right">{!! ovidiuro\myfinance2\App\Services\MoneyFormat::get_formatted_balance($quoteData['currency'], $quoteData['fiftyTwoWeekHigh']) !!}</td>
                        <td class="text-right" data-order="{{ - $quoteData['fiftyTwoWeekHighChangePercent'] * 100 }}">{!! ovidiuro\myfinance2\App\Services\MoneyFormat::get_formatted_52wk_high_percentage(- $quoteData['fiftyTwoWeekHighChangePercent'] * 100, count($quoteData['open_positions']) > 0) !!}</td>
                        {{--
                        <td class="text-right">{{ $quoteData['item']->id }}</td>
                        <td>{{ $quoteData['item']->timestamp }}</td>
                        <td>{{ $quoteData['item']->description }}</td>
                        <td>{{ $quoteData['item']->created_at }}</td>
                        <td>{{ $quoteData['item']->updated_at }}</td>
                        --}}
                        {{--
                        <td>
                            <a class="btn btn-sm btn-outline-info btn-block" href="{{ route('myfinance2::watchlist-symbols.show', $quoteData['item']->id) }}" data-bs-toggle="tooltip" title="{{ trans('myfinance2::general.tooltips.show-item', ['type' => 'Watchlist Symbol']) }}">
                                {!! trans('myfinance2::general.buttons.show') !!}
                            </a>
                        </td>
                        --}}
                        <td>
                        @if( !empty($quoteData['open_positions']) )
                            @foreach( $quoteData['open_positions'] as $key => $openPosition )
                            <div class="row">
                                <div class="col-sm">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="card-title">Acc: {{ $openPosition['account'] }}</div>
                                            <div class="card-text">
                                                Quantity: {{ $openPosition['quantity'] }}<br />
                                                Cost: {!! $openPosition['cost_in_account_currency'] !!}<br />
                                                Value: {!! $openPosition['market_value_in_account_currency'] !!}<br />
                                                Change value: {!! $openPosition['overall_change_in_account_currency'] !!}<br />
                                                Change %: {!! $openPosition['overall_change_in_percentage'] !!}<br />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @if (array_key_last($quoteData['open_positions']) != $key)
                            <div class="clearfix mb-1"></div>
                            @endif
                            @endforeach
                        @endif
                        </td>
                        <td>
                            <a class="btn btn-sm btn-outline-secondary btn-block" href="{{ route('myfinance2::watchlist-symbols.edit', $quoteData['item']->id) }}" data-bs-toggle="tooltip" title="{{ trans('myfinance2::general.tooltips.edit-item', ['type' => 'Watchlist Symbol']) }}">
                                {!! trans('myfinance2::general.buttons.edit') !!}
                            </a>
                        </td>
                        <td>
                            @include('myfinance2::watchlistsymbols.forms.delete-sm', ['type' => 'Watchlist Symbol', 'id' => $quoteData['item']->id])
                        </td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
    <div class="clearfix mb-3"></div>
</div>

