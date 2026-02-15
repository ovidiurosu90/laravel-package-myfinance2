@use('ovidiuro\myfinance2\App\Services\MoneyFormat')

@php
    // 1. Virtual funding accounts (from Funding card)
    $transferredKeyword = 'Transferred positions funding';
    $virtualFundingAccounts = [];
    foreach ($sourceAccounts as $accountId => $data) {
        $isTransferred = str_contains(
            strtolower($data['account']->description ?? ''),
            strtolower($transferredKeyword)
        );
        if ($isTransferred) {
            $virtualFundingAccounts[$accountId] = $data;
        }
    }

    // 2. Gains annotations per year (from Gains Per Year card)
    $gainsAnnotations = config('trades.gains_annotations', []);
    $allYears = array_unique(array_merge(
        array_keys($gainsPerYear ?? []),
        array_keys($dividendsPerYear ?? [])
    ));
    sort($allYears);

    $annotatedPerYear = [];
    $annotatedGrandTotal = 0;

    // Build detailed timeline from gains annotations
    $timeline = [];
    foreach ($allYears as $year) {
        $yearGains = $gainsPerYear[$year] ?? [];
        $yearAnnotatedEur = 0;
        $yearHasAnnotation = false;

        foreach ($yearGains as $accId => $syms) {
            foreach ($syms as $sym => $totals) {
                $annotation = $gainsAnnotations[$year][$accId][$sym]
                    ?? null;
                if (!$annotation) {
                    continue;
                }
                $yearHasAnnotation = true;
                $gain = $totals['total_gain_in_account_currency'];
                $rateData = $eurRatesPerYear[$year][$accId] ?? [];
                $eurRate = $rateData['exchange_rate'] ?? null;
                $gainEur = $eurRate !== null
                    ? $gain * $eurRate : $gain;
                $yearAnnotatedEur += $gainEur;

                $accountName = $totals['accountModel']->name
                    ?? "Account $accId";
                $timeline[] = [
                    'year' => $year,
                    'account' => $accountName,
                    'symbol' => $sym,
                    'gain_eur' => $gainEur,
                    'reason' => $annotation,
                ];
            }
        }

        if ($yearHasAnnotation) {
            $annotatedPerYear[$year] = $yearAnnotatedEur;
            $annotatedGrandTotal += $yearAnnotatedEur;
        }
    }

    // 3. Top winners with transfers
    $transferredAccounts = array_filter(
        $topWinners['topAccounts'] ?? [],
        fn($acc) => ($acc['transferred_eur'] ?? 0) != 0
    );
    $transferredSymbols = array_filter(
        $topWinners['topSymbols'] ?? [],
        fn($sym) => ($sym['transferred_eur'] ?? 0) != 0
    );

    // 4. Returns adjustment - virtual accounts with per-year details
    $virtualAccountsConfig = config('trades.virtual_accounts', []);
    $virtualCumulativeEur = $overviewData['virtualCumulativeTotal']['EUR']
        ?? 0;
    $virtualCumulativeUsd = $overviewData['virtualCumulativeTotal']['USD']
        ?? 0;

    // Build per-year virtual account details (reasons + amounts)
    $virtualPerYear = [];
    foreach ($virtualAccountsConfig as $id => $config) {
        $returns = $config['returns'] ?? [];
        foreach ($returns as $yr => $yearReturns) {
            $eurReturn = $yearReturns['EUR'] ?? 0;
            $usdReturn = $yearReturns['USD'] ?? 0;
            $reason = $yearReturns['reason'] ?? null;
            if ($eurReturn == 0 && $usdReturn == 0) {
                continue;
            }
            $virtualPerYear[$yr] = [
                'name' => $config['name'] ?? $id,
                'EUR' => $eurReturn,
                'USD' => $usdReturn,
                'reason' => $reason,
            ];
        }
    }
    krsort($virtualPerYear);

    // Check if there's any transfer data at all
    $hasTransferData = !empty($virtualFundingAccounts)
        || !empty($annotatedPerYear)
        || !empty($transferredAccounts)
        || !empty($transferredSymbols)
        || $virtualCumulativeEur != 0;
@endphp

@if($hasTransferData)
<div class="col-12">
    <div class="card">
        <div class="card-header">
            <span id="card_title" style="cursor: pointer;"
                data-bs-toggle="collapse"
                data-bs-target="#transferred-positions-body">
                <i class="fa-solid fa-shuffle me-1"
                    style="font-size: 0.8rem; color: #6c757d;"></i>
                {!! trans('myfinance2::overview.cards.'
                          . 'transferred-positions.title') !!}
                <i class="fa fa-chevron-right ms-2"
                    id="transferred-positions-chevron"></i>
            </span>
        </div>
        <div id="transferred-positions-body" class="collapse">
            <div class="card-body py-2 px-3">

                {{-- Row 1: Intro + bullets | Virtual Funding --}}
                <div class="row">
                    <div class="col-md-8">
                        <p class="text-muted mb-1"
                            style="font-size: 0.85rem;">
                            Some positions were acquired
                            externally (e.g. employer stock
                            grants), held in one brokerage
                            account, then transferred in-kind
                            to another broker where they were
                            eventually sold. Below is how these
                            transfers are reflected across the
                            dashboard.
                        </p>
                        <ul class="text-muted mb-0"
                            style="font-size: 0.8rem;
                                   padding-left: 1.2rem;">
                            <li>
                                The funding source (Virtual
                                Funding) represents the original
                                cost basis &mdash; the value at
                                which the shares were first
                                acquired
                            </li>
                            <li>
                                When shares transfer between
                                brokers, a synthetic deposit
                                (receiving account) and
                                withdrawal (sending account) are
                                recorded at the transfer-date
                                market value
                            </li>
                            <li>
                                Realized gains are attributed to
                                whichever account held the shares
                                during the price movement
                            </li>
                            <li>
                                At the portfolio level, the
                                transfer itself has zero net
                                impact on total returns &mdash;
                                only the actual market gains are
                                reflected
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        @include(
                            'myfinance2::overview.cards.partials.'
                            . 'transferred-funding'
                        )
                    </div>
                </div>

                {{-- Row 2: Gains timeline | Top Winners --}}
                <hr class="my-2">
                <div class="row">
                    @if(!empty($timeline))
                    <div class="col-md-8">
                        <h6 class="text-muted mb-1"
                            style="font-size: 0.85rem;">
                            <i class="fa-solid fa-timeline me-1"
                                style="font-size: 0.7rem;"></i>
                            Gains from Transferred Positions
                        </h6>
                        <table class="table table-sm
                                      table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Year</th>
                                    <th>Account</th>
                                    <th>Symbol</th>
                                    <th class="text-right
                                               table-warning">
                                        Gain &euro;
                                    </th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($timeline as $entry)
                                <tr>
                                    <td>{{ $entry['year'] }}</td>
                                    <td>
                                        {{ $entry['account'] }}
                                    </td>
                                    <td>
                                        {{ $entry['symbol'] }}
                                    </td>
                                    <td class="text-right
                                        text-nowrap
                                        table-warning">
                                        {!! MoneyFormat
                                            ::get_formatted_gain(
                                                '&euro;',
                                                $entry['gain_eur']
                                        ) !!}
                                    </td>
                                    <td class="text-muted"
                                        style="font-size:0.75rem">
                                        {{ $entry['reason'] }}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="table-warning">
                                    <td colspan="3">
                                        <strong>Total</strong>
                                    </td>
                                    <td class="text-right
                                               text-nowrap">
                                        <strong>
                                        {!! MoneyFormat
                                            ::get_formatted_gain(
                                                '&euro;',
                                                $annotatedGrandTotal
                                        ) !!}
                                        </strong>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    @endif
                    <div class="col-md-4">
                        @include(
                            'myfinance2::overview.cards.partials.'
                            . 'transferred-winners'
                        )
                    </div>
                </div>

                {{-- Returns Adjustment (if exists) --}}
                @if(!empty($virtualPerYear))
                <div class="row mt-2">
                    <div class="col-12">
                        <h6 class="text-muted mb-1"
                            style="font-size: 0.85rem;">
                            <i class="fa-solid fa-chart-line
                                      me-1"
                                style="font-size: 0.7rem;"></i>
                            Returns Adjustment
                        </h6>
                        <p class="text-muted mb-1"
                            style="font-size: 0.75rem;">
                            Adjustments applied to total
                            portfolio returns (Returns Overview)
                            to account for transfers. Per-account
                            returns are unaffected.
                        </p>
                        @foreach($virtualPerYear as $yr => $detail)
                        <div class="card mb-1"
                            style="border-style: dashed;">
                            <div class="card-header py-1 px-2"
                                style="background-color: #f8f9fa;
                                       font-size: 0.8rem;">
                                <div style="display: flex;
                                    justify-content:space-between;
                                    align-items: center;">
                                    <span>
                                        {{ $detail['name'] }}
                                        &mdash; {{ $yr }}
                                    </span>
                                    <span class="text-nowrap">
                                        {!! MoneyFormat
                                            ::get_formatted_gain(
                                                '&euro;',
                                                $detail['EUR']
                                        ) !!}
                                        &nbsp;/&nbsp;
                                        {!! MoneyFormat
                                            ::get_formatted_gain(
                                                '&#36;',
                                                $detail['USD']
                                        ) !!}
                                    </span>
                                </div>
                            </div>
                            @if($detail['reason'])
                            <div class="card-body py-1 px-2">
                                <p class="text-muted mb-0"
                                    style="font-size: 0.75rem;">
                                    {{ $detail['reason'] }}
                                </p>
                            </div>
                            @endif
                        </div>
                        @endforeach
                        @if($virtualCumulativeEur != 0)
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr class="table-warning">
                                    <td>
                                        <strong>
                                            Cumulative
                                        </strong>
                                    </td>
                                    <td class="text-right
                                               text-nowrap">
                                        <strong>
                                        {!! MoneyFormat
                                            ::get_formatted_gain(
                                                '&euro;',
                                                $virtualCumulativeEur
                                        ) !!}
                                        &nbsp;/&nbsp;
                                        {!! MoneyFormat
                                            ::get_formatted_gain(
                                                '&#36;',
                                                $virtualCumulativeUsd
                                        ) !!}
                                        </strong>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        @endif
                    </div>
                </div>
                @endif

            </div>
        </div>
    </div>
</div>
@endif
