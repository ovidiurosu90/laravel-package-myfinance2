@use('ovidiuro\myfinance2\App\Services\MoneyFormat')

<div class="col-4 ps-1">
    <div class="card">
        <div class="card-header">
            <span id="card_title">
                {!! trans('myfinance2::overview.cards.'
                          . 'top-winners.title') !!}
            </span>
        </div>
        <div class="card-body p-0">
            <div class="list-group-flush flex-fill">
                @if(count($topWinners['topAccounts']) > 0
                    || count($topWinners['topSymbols']) > 0)

                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Account</th>
                            <th scope="col"
                                class="text-right table-warning">
                                Total &euro;
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($topWinners['topAccounts'] as $i => $acc)
                        @php
                            $perYear = $acc['per_year'] ?? [];
                            krsort($perYear);
                            $tooltipLines = [];
                            foreach ($perYear as $y => $amt) {
                                $tooltipLines[] = $y . ': '
                                    . number_format($amt, 2)
                                    . ' &euro;';
                            }
                            $tooltip = implode(
                                '<br>', $tooltipLines
                            );
                        @endphp
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>
                                {{ $acc['name'] }}
                                @if(($acc['transferred_eur'] ?? 0) != 0)
                                    <i class="fa-solid fa-shuffle ms-1"
                                        style="font-size: 0.7rem;
                                               color: #6c757d;"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        data-bs-custom-class="big-tooltips"
                                        data-bs-html="true"
                                        data-bs-title="{{ trans(
                                            'myfinance2::overview'
                                            . '.tooltips'
                                            . '.transferred-year',
                                            ['amount' => number_format(
                                                $acc['transferred_eur'], 2
                                            ) . ' &euro;']
                                        ) }}">
                                    </i>
                                @endif
                            </td>
                            <td class="text-right text-nowrap
                                       table-warning">
                                <span
                                    @if($tooltip)
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="left"
                                        data-bs-custom-class="big-tooltips"
                                        data-bs-html="true"
                                        data-bs-title="{{ $tooltip }}"
                                    @endif>
                                    {!! MoneyFormat
                                        ::get_formatted_gain(
                                            '&euro;',
                                            $acc['total_eur']
                                    ) !!}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                <div style="height: 0; overflow: visible;">
                    <hr style="margin: 0; border: 0;
                               border-top: 2px solid
                                   rgba(13, 110, 253, 0.5);
                               position: relative;
                               z-index: 1;">
                </div>
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Symbol</th>
                            <th scope="col"
                                class="text-right table-warning">
                                Total &euro;
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($topWinners['topSymbols'] as $i => $sym)
                        @php
                            $perYear = $sym['per_year'] ?? [];
                            krsort($perYear);
                            $tooltipLines = [];
                            foreach ($perYear as $y => $amt) {
                                $tooltipLines[] = $y . ': '
                                    . number_format($amt, 2)
                                    . ' &euro;';
                            }
                            $tooltip = implode(
                                '<br>', $tooltipLines
                            );
                        @endphp
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>
                                {{ $sym['name'] }}
                                @if(($sym['transferred_eur'] ?? 0) != 0)
                                    <i class="fa-solid fa-shuffle ms-1"
                                        style="font-size: 0.7rem;
                                               color: #6c757d;"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        data-bs-custom-class="big-tooltips"
                                        data-bs-html="true"
                                        data-bs-title="{{ trans(
                                            'myfinance2::overview'
                                            . '.tooltips'
                                            . '.transferred-year',
                                            ['amount' => number_format(
                                                $sym['transferred_eur'], 2
                                            ) . ' &euro;']
                                        ) }}">
                                    </i>
                                @endif
                            </td>
                            <td class="text-right text-nowrap
                                       table-warning">
                                <span
                                    @if($tooltip)
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="left"
                                        data-bs-custom-class="big-tooltips"
                                        data-bs-html="true"
                                        data-bs-title="{{ $tooltip }}"
                                    @endif>
                                    {!! MoneyFormat
                                        ::get_formatted_gain(
                                            '&euro;',
                                            $sym['total_eur']
                                    ) !!}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                @else
                <p class="m-3">
                    {{ trans('myfinance2::overview.cards.'
                             . 'top-winners.no-items') }}
                </p>
                @endif
            </div>
        </div>
    </div>
</div>
