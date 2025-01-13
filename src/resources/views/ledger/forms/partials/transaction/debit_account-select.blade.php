<div class="form-group required has-feedback row
    {{ $errors->has('debit_account_id') ? 'has-error' : '' }}">
    <label for="debit_account" class="col-12 control-label">
        {{ trans('myfinance2::ledger.forms.transaction-form.debit_account.label') }}
    </label>
    <div class="col-9">
        <select name="debit_account_id" id="transaction-debit_account-select"
            required>
            <option value="">{{ trans('myfinance2::ledger.forms.transaction-form.'
                                      . 'debit_account.placeholder') }}</option>
            @foreach ($accounts as $account)
            <option @if ($debit_account_id == $account->id) selected @endif
                value="{{ $account->id }}">
                {{ $account->name }} ({!! $account->currency->display_code !!})
            </option>
            @endforeach
        </select>
    </div>
    <div class="col-3 p-0">
        <input id="toggle-transaction-debit_account-select" type="checkbox"
            data-bs-toggle="toggle"
            data-ontitle="{{ trans('myfinance2::ledger.tooltips.'
                                   . 'disable-transaction-debit_account-select') }}"
            data-offtitle="{{ trans('myfinance2::ledger.tooltips.'
                                   . 'enable-transaction-debit_account-select') }}">
    </div>
    @if ($errors->has('debit_account_id'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('debit_account_id') }}</strong>
            </span>
        </div>
    @endif
</div>

