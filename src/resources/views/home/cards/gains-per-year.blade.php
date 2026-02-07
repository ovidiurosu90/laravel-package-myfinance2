<div class="col ps-1">
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
                    @php
                        $gainsAnnotations = config('trades.gains_annotations', []);
                    @endphp
                    <tbody>
                    @foreach($gainsPerYear as $year => $accounts)
                        @php
                            $yearTotals = [];
                            $annotatedGains = [];
                        @endphp
                        @foreach($accounts as $account => $symbols)
                            @foreach($symbols as $symbol => $totals)
                                @php
                                    $annotation = $gainsAnnotations[$year][$account][$symbol]
                                        ?? null;
                                    if ($annotation) {
                                        $currency = $totals['accountModel']->currency;
                                        $annotatedGains[] = [
                                            'symbol' => $symbol,
                                            'gain' => $totals['total_gain_in_account_currency'],
                                            'displayCode' => $currency->display_code,
                                            'isoCode' => $currency->iso_code,
                                            'reason' => $annotation,
                                        ];
                                    }
                                @endphp
                        <tr>
                            <td scope="row">
                                {{ $totals['accountModel']->name }}
                                ({!! $totals['accountModel']->currency
                                                            ->display_code !!})
                            </td>
                            <td scope="row">
                                {{ $symbol }}
                                @if($annotation)
                                    <i class="fa-solid fa-shuffle"
                                        style="font-size: 0.75rem; color: #6c757d;"
                                        data-bs-toggle="tooltip"
                                        title="{{ $annotation }}"></i>
                                @endif
                            </td>
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
                        @if(!empty($annotatedGains))
                            @php
                                $annotatedTotals = [];
                                foreach ($annotatedGains as $ag) {
                                    if (!isset($annotatedTotals[$ag['isoCode']])) {
                                        $annotatedTotals[$ag['isoCode']] = 0;
                                    }
                                    $annotatedTotals[$ag['isoCode']] += $ag['gain'];
                                }
                            @endphp
                            <tr style="background-color: #f8f9fa;">
                                <td colspan="2" class="text-muted" style="font-size: 0.85rem;">
                                    <i class="fa-solid fa-shuffle me-1"
                                        style="font-size: 0.7rem;"></i>
                                    of which from transferred positions
                                </td>
                                <td class="text-right text-muted" style="font-size: 0.85rem;">
                                    @foreach($annotatedTotals as $currency => $total)
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
                        @endif
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

