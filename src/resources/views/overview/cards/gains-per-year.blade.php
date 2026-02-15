@use('ovidiuro\myfinance2\App\Services\MoneyFormat')

<div class="col-8 pe-1">
    <div class="card">
        <div class="card-header">
            <span id="card_title">
                {!! trans('myfinance2::overview.cards.'
                          . 'gains-per-year.title') !!}
            </span>
        </div>
        <div class="card-body p-0">
            <div class="list-group-flush flex-fill">
                @php
                    $allYears = array_unique(array_merge(
                        array_keys($gainsPerYear ?? []),
                        array_keys($dividendsPerYear ?? [])
                    ));
                    sort($allYears);
                    $gainsAnnotations = config(
                        'trades.gains_annotations', []
                    );
                @endphp
                @if(count($allYears) != 0)
                <table class="table table-sm table-striped
                              mb-0" id="gainsPerYearTable">
                    <thead>
                        <tr>
                            <th scope="col">Year / Account /
                                Symbol</th>
                            <th scope="col" class="text-right"
                                data-bs-toggle="tooltip"
                                title="Gains from selling stocks">
                                Stock gain
                            </th>
                            <th scope="col"
                                class="text-right table-info"
                                data-bs-toggle="tooltip"
                                title="Stock gains converted to EUR
                                       using the last exchange rate
                                       of the year">
                                Stock gain &euro;
                            </th>
                            <th scope="col" class="text-right"
                                data-bs-toggle="tooltip"
                                title="Total Dividends (after
                                       deducting fees) in account
                                       currency">
                                Dividends
                            </th>
                            <th scope="col"
                                class="text-right table-info"
                                data-bs-toggle="tooltip"
                                title="Dividends converted to EUR
                                       using the last exchange rate
                                       of the year">
                                Dividends &euro;
                            </th>
                            <th scope="col"
                                class="text-right table-warning"
                                data-bs-toggle="tooltip"
                                title="Stock gain &euro; + Dividends
                                       &euro;">
                                Total gain &euro;
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                    @php
                        $grandStockGainEur = 0;
                        $grandDivEur = 0;
                    @endphp
                    @foreach($allYears as $year)
                        @php
                            $yearGainTotals = [];
                            $yearGainEurTotal = 0;
                            $yearDividendTotals = [];
                            $yearDivEurTotal = 0;
                            $yearGains =
                                $gainsPerYear[$year] ?? [];
                            $yearDividends =
                                $dividendsPerYear[$year] ?? [];

                            $mergedByAccount = [];

                            foreach ($yearGains as $accId => $syms) {
                                if (!isset($mergedByAccount[$accId])) {
                                    $firstSym = array_key_first($syms);
                                    $mergedByAccount[$accId] = [
                                        'accountModel' =>
                                            $syms[$firstSym]
                                                ['accountModel'],
                                        'symbols' => [],
                                    ];
                                }
                                foreach ($syms as $sym => $totals) {
                                    $mergedByAccount[$accId]
                                        ['symbols'][$sym] = [
                                            'gain' => $totals[
                                                'total_gain_in_'
                                                . 'account_currency'
                                            ],
                                            'dividend' => 0,
                                        ];
                                }
                            }

                            foreach (
                                $yearDividends as $accId => $syms
                            ) {
                                if (!isset($mergedByAccount[$accId])) {
                                    $firstSym = array_key_first($syms);
                                    $mergedByAccount[$accId] = [
                                        'accountModel' =>
                                            $syms[$firstSym]
                                                ['accountModel'],
                                        'symbols' => [],
                                    ];
                                }
                                foreach ($syms as $sym => $totals) {
                                    $divAmount = $totals[
                                        'total_dividend_in_'
                                        . 'account_currency'
                                    ];
                                    if (isset($mergedByAccount[$accId]
                                        ['symbols'][$sym])
                                    ) {
                                        $mergedByAccount[$accId]
                                            ['symbols'][$sym]
                                            ['dividend'] = $divAmount;
                                    } else {
                                        $mergedByAccount[$accId]
                                            ['symbols'][$sym] = [
                                                'gain' => 0,
                                                'dividend' => $divAmount,
                                            ];
                                    }
                                }
                            }

                            uasort(
                                $mergedByAccount,
                                fn($a, $b) => strcasecmp(
                                    $a['accountModel']->name,
                                    $b['accountModel']->name
                                )
                            );

                            // Pre-compute account totals
                            $accountTotals = [];
                            $annotatedPerAccount = [];
                            $annotatedEurTotal = 0;
                            $yearHasAnnotation = false;
                            foreach (
                                $mergedByAccount as $accId => $accData
                            ) {
                                $accGain = 0;
                                $accDiv = 0;
                                $accAnnotatedGain = 0;
                                $hasAnnotation = false;
                                foreach (
                                    $accData['symbols'] as $sym => $row
                                ) {
                                    $annotation =
                                        $gainsAnnotations[$year][$accId]
                                            [$sym] ?? null;
                                    if ($annotation) {
                                        $accAnnotatedGain += $row['gain'];
                                        $hasAnnotation = true;
                                    }
                                    $accGain += $row['gain'];
                                    $accDiv += $row['dividend'];
                                }

                                // EUR conversion
                                $rateData =
                                    $eurRatesPerYear[$year][$accId]
                                        ?? [];
                                $eurRate =
                                    $rateData['exchange_rate'] ?? null;
                                $accGainEur = $eurRate !== null
                                    ? $accGain * $eurRate
                                    : $accGain;
                                $accDivEur = $eurRate !== null
                                    ? $accDiv * $eurRate
                                    : $accDiv;
                                $accAnnotatedEur = $eurRate !== null
                                    ? $accAnnotatedGain * $eurRate
                                    : $accAnnotatedGain;

                                if ($hasAnnotation) {
                                    $yearHasAnnotation = true;
                                    $annotatedPerAccount[$accId] = [
                                        'gain' => $accAnnotatedGain,
                                        'gain_eur' => $accAnnotatedEur,
                                        'displayCode' =>
                                            $accData['accountModel']
                                                ->currency->display_code,
                                    ];
                                    $annotatedEurTotal += $accAnnotatedEur;
                                }

                                $accountTotals[$accId] = [
                                    'gain' => $accGain,
                                    'gain_eur' => $accGainEur,
                                    'dividend' => $accDiv,
                                    'dividend_eur' => $accDivEur,
                                ];

                                $isoCode = $accData['accountModel']
                                    ->currency->iso_code;
                                if (empty($yearGainTotals[$isoCode])) {
                                    $yearGainTotals[$isoCode] = 0;
                                }
                                if (empty(
                                    $yearDividendTotals[$isoCode]
                                )) {
                                    $yearDividendTotals[$isoCode] = 0;
                                }
                                $yearGainTotals[$isoCode] += $accGain;
                                $yearDividendTotals[$isoCode] += $accDiv;
                                $yearGainEurTotal += $accGainEur;
                                $yearDivEurTotal += $accDivEur;
                            }
                            $yearTotalEur =
                                $yearGainEurTotal + $yearDivEurTotal;
                            $grandStockGainEur += $yearGainEurTotal;
                            $grandDivEur += $yearDivEurTotal;
                        @endphp
                        {{-- Level 1: Year row (always visible) --}}
                        <tr class="gpy-year"
                            data-gpy-year="{{ $year }}"
                            style="cursor: pointer;">
                            <th scope="row">
                                <i class="fa-solid fa-chevron-right
                                          gpy-chevron me-1"
                                    style="font-size: 0.7rem;
                                           transition:
                                               transform 0.2s;">
                                </i>
                                {{ $year }}
                                @if($yearHasAnnotation)
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
                                                $annotatedEurTotal, 2
                                            ) . ' &euro;']
                                        ) }}">
                                    </i>
                                @endif
                            </th>
                            <td></td>
                            <td class="text-right table-info">
                                @if($yearGainEurTotal != 0)
                                    {!! MoneyFormat
                                        ::get_formatted_gain(
                                            '&euro;',
                                            $yearGainEurTotal
                                    ) !!}
                                @endif
                            </td>
                            <td></td>
                            <td class="text-right table-info">
                                @if($yearDivEurTotal != 0)
                                    {!! MoneyFormat
                                        ::get_formatted_gain(
                                            '&euro;',
                                            $yearDivEurTotal
                                    ) !!}
                                @endif
                            </td>
                            <td class="text-right table-warning">
                                @if($yearTotalEur != 0)
                                    {!! MoneyFormat
                                        ::get_formatted_gain(
                                            '&euro;',
                                            $yearTotalEur
                                    ) !!}
                                @endif
                            </td>
                        </tr>
                        {{-- Level 2: Account rows (hidden) --}}
                        @foreach(
                            $mergedByAccount as $accId => $accData
                        )
                            @php
                                $accountModel = $accData['accountModel'];
                                $displayCode =
                                    $accountModel->currency->display_code;
                                $accGain =
                                    $accountTotals[$accId]['gain'];
                                $accGainEur =
                                    $accountTotals[$accId]['gain_eur'];
                                $accDiv =
                                    $accountTotals[$accId]['dividend'];
                                $accDivEur =
                                    $accountTotals[$accId]
                                        ['dividend_eur'];
                                $accTotalEur = $accGainEur + $accDivEur;
                                $rateData =
                                    $eurRatesPerYear[$year][$accId]
                                        ?? [];
                                $accTooltip = '';
                                if (!empty(
                                    $rateData['conversion_pair']
                                )) {
                                    $accTooltip = 'EURUSD: '
                                        . number_format(
                                            $rateData['eurusd_rate'], 4
                                        )
                                        . '<br>'
                                        . $rateData['conversion_pair']
                                        . ': '
                                        . number_format(
                                            $rateData['exchange_rate'], 4
                                        );
                                }
                            @endphp
                        <tr class="gpy-year-child gpy-account
                                   table-active"
                            style="--bs-table-active-bg:
                                       rgba(0, 0, 0, 0.04);
                                   display: none;
                                   cursor: pointer;"
                            data-gpy-parent-year="{{ $year }}"
                            data-gpy-account="{{ $year }}-{{ $accId }}">
                            @php
                                $accAnnotation =
                                    $annotatedPerAccount[$accId] ?? null;
                            @endphp
                            <td style="padding-left: 1.5rem;">
                                <i class="fa-solid fa-chevron-right
                                          gpy-chevron me-1"
                                    style="font-size: 0.65rem;
                                           transition:
                                               transform 0.2s;">
                                </i>
                                <strong>
                                    {{ $accountModel->name }}
                                    ({!! $displayCode !!})
                                </strong>
                                @if($accAnnotation)
                                    @php
                                        $isEurAccount =
                                            $accountModel->currency
                                                ->iso_code === 'EUR';
                                        $accTransTooltip = $isEurAccount
                                            ? trans(
                                                'myfinance2::overview'
                                                . '.tooltips'
                                                . '.transferred-account-eur',
                                                ['amount' => number_format(
                                                    $accAnnotation['gain'], 2
                                                ) . ' '
                                                . strip_tags(
                                                    $accAnnotation[
                                                        'displayCode'
                                                    ]
                                                )]
                                            )
                                            : trans(
                                                'myfinance2::overview'
                                                . '.tooltips'
                                                . '.transferred-account',
                                                [
                                                    'amount' => number_format(
                                                        $accAnnotation['gain'],
                                                        2
                                                    ) . ' '
                                                    . strip_tags(
                                                        $accAnnotation[
                                                            'displayCode'
                                                        ]
                                                    ),
                                                    'amount_eur' =>
                                                        number_format(
                                                            $accAnnotation[
                                                                'gain_eur'
                                                            ], 2
                                                        ) . ' &euro;',
                                                ]
                                            );
                                    @endphp
                                    <i class="fa-solid fa-shuffle ms-1"
                                        style="font-size: 0.65rem;
                                               color: #6c757d;"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        data-bs-custom-class="big-tooltips"
                                        data-bs-html="true"
                                        data-bs-title="{{ $accTransTooltip }}">
                                    </i>
                                @endif
                            </td>
                            <td class="text-right">
                                @if($accGain != 0)
                                    <strong>
                                    {!! MoneyFormat
                                        ::get_formatted_gain(
                                            $displayCode,
                                            $accGain
                                    ) !!}
                                    </strong>
                                @endif
                            </td>
                            <td class="text-right table-info">
                                @if($accGainEur != 0)
                                    <strong>
                                    <span
                                        @if($accTooltip)
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                            data-bs-custom-class="big-tooltips"
                                            data-bs-html="true"
                                            data-bs-title="{!! $accTooltip !!}"
                                        @endif>
                                        {!! MoneyFormat
                                            ::get_formatted_gain(
                                                '&euro;',
                                                $accGainEur
                                        ) !!}
                                    </span>
                                    </strong>
                                @endif
                            </td>
                            <td class="text-right">
                                @if($accDiv != 0)
                                    <strong>
                                    {!! MoneyFormat
                                        ::get_formatted_gain(
                                            $displayCode,
                                            $accDiv
                                    ) !!}
                                    </strong>
                                @endif
                            </td>
                            <td class="text-right table-info">
                                @if($accDivEur != 0)
                                    <strong>
                                    <span
                                        @if($accTooltip)
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                            data-bs-custom-class="big-tooltips"
                                            data-bs-html="true"
                                            data-bs-title="{!! $accTooltip !!}"
                                        @endif>
                                        {!! MoneyFormat
                                            ::get_formatted_gain(
                                                '&euro;',
                                                $accDivEur
                                        ) !!}
                                    </span>
                                    </strong>
                                @endif
                            </td>
                            <td class="text-right table-warning">
                                @if($accTotalEur != 0)
                                    <strong>
                                    <span
                                        @if($accTooltip)
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                            data-bs-custom-class="big-tooltips"
                                            data-bs-html="true"
                                            data-bs-title="{!! $accTooltip !!}"
                                        @endif>
                                        {!! MoneyFormat
                                            ::get_formatted_gain(
                                                '&euro;',
                                                $accTotalEur
                                        ) !!}
                                    </span>
                                    </strong>
                                @endif
                            </td>
                        </tr>
                            {{-- Level 3: Symbol rows (hidden) --}}
                            @foreach(
                                $accData['symbols'] as $sym => $row
                            )
                                @php
                                    $annotation =
                                        $gainsAnnotations[$year][$accId]
                                            [$sym] ?? null;
                                    $eurRate =
                                        $rateData['exchange_rate']
                                            ?? null;
                                    $symGainEur = $eurRate !== null
                                        ? $row['gain'] * $eurRate
                                        : $row['gain'];
                                    $symDivEur = $eurRate !== null
                                        ? $row['dividend'] * $eurRate
                                        : $row['dividend'];
                                    $symTotalEur =
                                        $symGainEur + $symDivEur;
                                @endphp
                        <tr class="gpy-account-child"
                            data-gpy-parent-account="{{ $year }}-{{ $accId }}"
                            style="display: none;">
                            <td style="padding-left: 3rem;">
                                {{ $sym }}
                                @if($annotation)
                                    <i class="fa-solid fa-shuffle"
                                        style="font-size: 0.75rem;
                                               color: #6c757d;"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        data-bs-custom-class="big-tooltips"
                                        data-bs-html="true"
                                        data-bs-title="{{ trans(
                                            'myfinance2::overview'
                                            . '.tooltips'
                                            . '.transferred-symbol',
                                            [
                                                'annotation' => $annotation,
                                                'amount' => number_format(
                                                    $row['gain'], 2
                                                ) . ' '
                                                . strip_tags($displayCode),
                                            ]
                                        ) }}">
                                    </i>
                                @endif
                            </td>
                            <td class="text-right">
                                @if($row['gain'] != 0)
                                    {!! MoneyFormat
                                        ::get_formatted_gain(
                                            $displayCode,
                                            $row['gain']
                                    ) !!}
                                @endif
                            </td>
                            <td class="text-right table-info">
                                @if($row['gain'] != 0)
                                    <span
                                        @if($accTooltip)
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                            data-bs-custom-class="big-tooltips"
                                            data-bs-html="true"
                                            data-bs-title="{!! $accTooltip !!}"
                                        @endif>
                                        {!! MoneyFormat
                                            ::get_formatted_gain(
                                                '&euro;',
                                                $symGainEur
                                        ) !!}
                                    </span>
                                @endif
                            </td>
                            <td class="text-right">
                                @if($row['dividend'] != 0)
                                    {!! MoneyFormat
                                        ::get_formatted_gain(
                                            $displayCode,
                                            $row['dividend']
                                    ) !!}
                                @endif
                            </td>
                            <td class="text-right table-info">
                                @if($row['dividend'] != 0)
                                    <span
                                        @if($accTooltip)
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                            data-bs-custom-class="big-tooltips"
                                            data-bs-html="true"
                                            data-bs-title="{!! $accTooltip !!}"
                                        @endif>
                                        {!! MoneyFormat
                                            ::get_formatted_gain(
                                                '&euro;',
                                                $symDivEur
                                        ) !!}
                                    </span>
                                @endif
                            </td>
                            <td class="text-right table-warning">
                                @if($symTotalEur != 0)
                                    <span
                                        @if($accTooltip)
                                            data-bs-toggle="tooltip"
                                            data-bs-placement="top"
                                            data-bs-custom-class="big-tooltips"
                                            data-bs-html="true"
                                            data-bs-title="{!! $accTooltip !!}"
                                        @endif>
                                        {!! MoneyFormat
                                            ::get_formatted_gain(
                                                '&euro;',
                                                $symTotalEur
                                        ) !!}
                                    </span>
                                @endif
                            </td>
                        </tr>
                            @endforeach
                        @endforeach
                    @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="table-warning">
                            <td><strong>Total</strong></td>
                            <td></td>
                            <td class="text-right">
                                <strong>
                                    {!! MoneyFormat
                                        ::get_formatted_gain(
                                            '&euro;',
                                            $grandStockGainEur
                                    ) !!}
                                </strong>
                            </td>
                            <td></td>
                            <td class="text-right">
                                <strong>
                                    {!! MoneyFormat
                                        ::get_formatted_gain(
                                            '&euro;',
                                            $grandDivEur
                                    ) !!}
                                </strong>
                            </td>
                            <td class="text-right">
                                <strong>
                                    {!! MoneyFormat
                                        ::get_formatted_gain(
                                            '&euro;',
                                            $grandStockGainEur
                                                + $grandDivEur
                                    ) !!}
                                </strong>
                            </td>
                        </tr>
                    </tfoot>
                </table>
                @else
                <p class="m-3">
                    {{ trans('myfinance2::overview.cards.'
                             . 'gains-per-year.no-items') }}
                </p>
                @endif
            </div>
        </div>
    </div>
</div>

<script type="module">
    const table = document.getElementById('gainsPerYearTable');
    if (table) {
        table.addEventListener('click', (e) => {
            const yearRow = e.target.closest('.gpy-year');
            const accountRow = e.target.closest('.gpy-account');

            if (accountRow) {
                const accountKey = accountRow
                    .dataset.gpyAccount;
                const chevron = accountRow
                    .querySelector('.gpy-chevron');
                const children = table.querySelectorAll(
                    `.gpy-account-child[data-gpy-parent-account`
                    + `="${accountKey}"]`
                );
                const isExpanded = chevron.style.transform
                    === 'rotate(90deg)';

                chevron.style.transform = isExpanded
                    ? '' : 'rotate(90deg)';
                children.forEach((child) => {
                    child.style.display = isExpanded
                        ? 'none' : '';
                });
                return;
            }

            if (yearRow) {
                const year = yearRow.dataset.gpyYear;
                const chevron = yearRow
                    .querySelector('.gpy-chevron');
                const children = table.querySelectorAll(
                    `.gpy-year-child[data-gpy-parent-year`
                    + `="${year}"]`
                );
                const isExpanded = chevron.style.transform
                    === 'rotate(90deg)';

                chevron.style.transform = isExpanded
                    ? '' : 'rotate(90deg)';

                if (isExpanded) {
                    children.forEach((child) => {
                        child.style.display = 'none';
                        const accChevron = child
                            .querySelector('.gpy-chevron');
                        if (accChevron) {
                            accChevron.style.transform = '';
                        }
                    });
                    const symChildren = table.querySelectorAll(
                        `.gpy-account-child`
                        + `[data-gpy-parent-account^="${year}-"]`
                    );
                    symChildren.forEach((child) => {
                        child.style.display = 'none';
                    });
                } else {
                    children.forEach((child) => {
                        child.style.display = '';
                    });
                }
            }
        });
    }
</script>
