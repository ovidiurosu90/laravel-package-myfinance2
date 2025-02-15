<div class="col-3">
    <div class="card">
        <div class="card-header">
            <div style="display: flex; justify-content: space-between;
                        align-items: center;">
                <span id="card_title">
                    <a target="_blank" href="{{ url('/funding') }}">
                        {!! trans('myfinance2::funding.titles.dashboard') !!}
                    </a>
                </span>
                <div class="float-right">
                    <a class="btn btn-sm"
                        href="{{ route('myfinance2::ledger-transactions.create') }}"
                        target="_blank" data-bs-toggle="tooltip"
                        title="{{ trans('myfinance2::general.tooltips.create-item',
                                        ['type' => 'Ledger Transaction']) }}">
                        <i class="fa fa-fw fa-plus" aria-hidden="true"></i>
                        {!! trans('myfinance2::general.buttons.create') !!}
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body p-0" style="height: 496px; overflow: auto">
            <div class="list-group-flush flex-fill">
                @if(count(array_keys($balances)) != 0)
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th scope="col">Account</th>
                            <th scope="col" class="text-right">Current Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($balances as $accountId => $balance)
                        <tr>
                            <td scope="row">
                                {{ $accounts[$accountId]->name }}
                                ({!! $accounts[$accountId]->currency
                                                          ->display_code !!})
                            </td>
                            <td class="text-right">
                                {!! ovidiuro\myfinance2\App\Services\MoneyFormat
                                ::get_formatted_gain(
                                    $accounts[$accountId]->currency->display_code,
                                    $balance) !!}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @else
                <p class="m-3">
                    {{ trans('myfinance2::home.cards.funding.no-items') }}
                </p>
                @endif
            </div>
        </div>
    </div>
</div>

