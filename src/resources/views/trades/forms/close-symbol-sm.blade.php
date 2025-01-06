<form action="{{ route('myfinance2::trades.close-symbol') }}" method="POST"
    accept-charset="utf-8" data-bs-toggle="tooltip"
    title="{{ trans('myfinance2::trades.tooltips.close-symbol', [
                'account' => $accountModel->name . ' (' .
                    $accountModel->currency->display_code . ')',
                'symbol' => $symbol]) }}">
    {{ csrf_field() }}
    {{ method_field('PATCH') }}
    <input type="hidden" name="account_id" value="{{ $accountModel->id }}" />
    <input type="hidden" name="symbol" value="{{ $symbol }}" />
    <button class="btn btn-block btn-outline-success btn-sm" type="button"
        data-bs-toggle="modal" data-bs-target="#confirm-delete-modal"
        data-title="{!! trans('myfinance2::trades.modals.close-symbol_modal_title',
                              ['account' => $account . ' ' . $accountCurrency]) !!}"
        data-message="{!! trans('myfinance2::trades.modals.'
                                . 'close-symbol_modal_message',
                                ['account' => $accountModel->name . ' (' .
                                    $accountModel->currency->display_code . ')',
                                 'symbol' => $symbol]) !!}" >
        {!! trans('myfinance2::trades.buttons.close-trade') !!}
    </button>
</form>

