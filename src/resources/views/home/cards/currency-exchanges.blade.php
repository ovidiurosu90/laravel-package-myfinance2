<div class="col-sm-8 mb-3 d-flex">
    <div class="card">
        <div class="card-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span id="card_title">
                    <a target="_blank" href="{{ url('/funding') }}">
                        {!! trans('myfinance2::home.cards.currency-exchanges.title') !!}
                    </a>
                </span>
                <div class="float-right">
                    <a class="btn btn-sm" href="{{ route('myfinance2::ledger-transactions.create') }}" target="_blank" data-bs-toggle="tooltip" title="{{ trans('myfinance2::general.tooltips.create-item', ['type' => 'Ledger Transaction']) }}">
                        <i class="fa fa-fw fa-plus" aria-hidden="true"></i>
                        {!! trans('myfinance2::general.buttons.create') !!}
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="list-group-flush flex-fill">
                @if(count(array_keys($currencyExchanges)) != 0)
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th scope="col" data-bs-toggle="tooltip" title="Debit transaction id">Id</th>
                            <th scope="col" data-bs-toggle="tooltip" title="Debit transaction summary">Summary</th>
                            <th scope="col" class="text-right" style="width: 112px" data-bs-toggle="tooltip" title="How much x I paid in the past for this amount of y">Cost</th>
                            <th scope="col" class="text-right" style="width: 112px" data-bs-toggle="tooltip" title="How much x I got now for this amount of y">Amount</th>
                            <th scope="col" class="text-right" style="width: 112px" data-bs-toggle="tooltip" title="How much x I gained from this transaction">Gain</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($currencyExchanges as $id => $data)
                        <tr>
                            <th scope="row">
                                <a class="btn btn-sm btn-outline-secondary btn-block" href="{{ route('myfinance2::ledger-transactions.edit', $id) }}" data-bs-toggle="tooltip" title="{{ trans('myfinance2::general.tooltips.edit-item', ['type' => 'Ledger Transaction']) }}" target="_blank">
                                    {{ $id }}
                                </a>
                            </th>
                            <td>
                                {{ $data['debit_transaction']->type }}
                                {!! ovidiuro\myfinance2\App\Services\MoneyFormat::get_formatted_balance($data['debit_transaction']->debit_currency, $data['debit_transaction']->amount) !!}
                                ON
                                {{ $data['debit_transaction']->timestamp->format(trans('myfinance2::general.date-format')) }}
                            </td>
                            <td class="text-right">
                                {!! ovidiuro\myfinance2\App\Services\MoneyFormat::get_formatted_gain($data['debit_transaction']->credit_currency, -abs($data['cost'])) !!}
                            </td>
                            <td class="text-right">
                                {!! ovidiuro\myfinance2\App\Services\MoneyFormat::get_formatted_gain($data['debit_transaction']->credit_currency, abs($data['amount'])) !!}
                            </td>
                            <td class="text-right">
                                {!! ovidiuro\myfinance2\App\Services\MoneyFormat::get_formatted_gain($data['debit_transaction']->credit_currency, $data['gain']) !!}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-top">
                            <td></td>
                            <td>@include('myfinance2::home.forms.currency-exchange-gain-estimator')</td>
                            <td class="text-right align-bottom" style="padding-bottom: 2.5rem" id="estimated-cost"></td>
                            <td class="text-right align-bottom" style="padding-bottom: 2.5rem" id="estimated-amount"></td>
                            <td class="text-right align-bottom" style="padding-bottom: 2.5rem" id="estimated-gain"></td>
                        </tr>
                        <tr>
                            <td></td>
                            <td>
                                <div class="alert alert-primary" role="alert">
                                    When EURUSD is small, you want to SELL USD (e.g. EURUSD 1.0636)
                                </div>
                                <div class="alert alert-primary" role="alert">
                                    When EURUSD is big, you want to BUY USD (e.g. EURUSD 1.1495)
                                </div>
                            </td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr class="border-top">
                            <td></td>
                            <td>
                                <h3>Data computed from previous ledger transactions</h2>
                                <h4>
                                    <strong>Currency balances</strong>:
                                    <span class="badge badge-light">{!! ovidiuro\myfinance2\App\Services\MoneyFormat::get_formatted_gain('EUR', $currencyBalances['EUR']) !!}</span>
                                    <span class="badge badge-light">{!! ovidiuro\myfinance2\App\Services\MoneyFormat::get_formatted_gain('USD', $currencyBalances['USD']) !!}</span>
                                </h4>
                                <h4>
                                    <strong>EURUSD exchange</strong>:
                                    <span class="badge badge-light">{{ @number_format(abs($currencyBalances['USD'] / $currencyBalances['EUR']), 4) }}</span>
                                </h4>
                            </td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                @else
                <p class="m-3">{{ trans('myfinance2::home.cards.currency-exchanges.no-items') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>

