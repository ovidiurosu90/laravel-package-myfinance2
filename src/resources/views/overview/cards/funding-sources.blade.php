@use('ovidiuro\myfinance2\App\Services\MoneyFormat')

<div class="col-4 pe-1">
    <div class="card">
        <div class="card-header">
            <span id="card_title">
                {!! trans('myfinance2::overview.cards.'
                          . 'funding-sources.title') !!}
            </span>
        </div>
        <div class="card-body p-0" style="height: 320px; overflow: auto">
            <div class="list-group-flush flex-fill">
                @if(count($sourceAccounts) > 0)
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th scope="col">Account</th>
                            <th scope="col" class="text-right">
                                Balance
                            </th>
                            <th scope="col"
                                class="text-right table-warning">
                                Balance &euro;
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($sourceAccounts as $accountId => $data)
                        @php
                            $tooltip = '';
                            if ($data['conversion_pair']) {
                                $tooltip = 'EURUSD: '
                                    . number_format(
                                        $data['eurusd_rate'], 4
                                    )
                                    . '<br>'
                                    . $data['conversion_pair']
                                    . ': '
                                    . number_format(
                                        $data['exchange_rate'], 4
                                    );
                            }
                            $transferredKeyword =
                                'Transferred positions funding';
                            $isTransferredFunding = str_contains(
                                strtolower(
                                    $data['account']->description ?? ''
                                ),
                                strtolower($transferredKeyword)
                            );
                        @endphp
                        <tr>
                            <td>
                                {{ $data['account']->name }}
                                ({!! $data['account']->currency
                                                     ->display_code !!})
                                @if($isTransferredFunding)
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
                                            . '.transferred-funding',
                                            ['keyword' =>
                                                $transferredKeyword]
                                        ) }}">
                                    </i>
                                @endif
                            </td>
                            <td class="text-right text-nowrap">
                                {!! MoneyFormat::get_formatted_gain(
                                        $data['account']->currency
                                                        ->display_code,
                                        $data['balance']) !!}
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
                                        $data['balance_in_eur']
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
                            <td class="text-right text-nowrap">
                                <strong>
                                    {!! MoneyFormat::get_formatted_gain(
                                        '&euro;',
                                        collect($sourceAccounts)->sum(
                                            'balance_in_eur'
                                        )
                                    ) !!}
                                </strong>
                            </td>
                        </tr>
                    </tfoot>
                </table>
                @else
                <p class="m-3">
                    {{ trans('myfinance2::overview.cards.'
                             . 'funding-sources.no-items') }}
                </p>
                @endif
            </div>
        </div>
    </div>
</div>

