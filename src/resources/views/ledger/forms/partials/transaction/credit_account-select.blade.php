<div class="form-group required has-feedback row {{ $errors->has('credit_account') ? ' has-error ' : '' }}">
    <label for="credit_account" class="col-12 control-label">
        {{ trans('myfinance2::ledger.forms.transaction-form.credit_account.label') }}
    </label>
    <div class="col-9">
        <select name="credit_account" id="transaction-credit_account-select" required>
            <option value="">{{ trans('myfinance2::ledger.forms.transaction-form.credit_account.placeholder') }}</option>
            @foreach ($creditAccounts as $creditAccountKey => $creditAccount)
                <option @if ($creditAccount == $credit_account) selected @endif value="{{ $creditAccountKey }}">
                    {{ $creditAccount }}
                </option>}
            @endforeach
        </select>
    </div>
    <div class="col-3 p-0">
        <input id="toggle-transaction-credit_account-select" type="checkbox" data-bs-toggle="toggle" data-ontitle="{{ trans('myfinance2::ledger.tooltips.disable-transaction-credit_account-select') }}" data-offtitle="{{ trans('myfinance2::ledger.tooltips.enable-transaction-credit_account-select') }}">
    </div>
    @if ($errors->has('credit_account'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('credit_account') }}</strong>
            </span>
        </div>
    @endif
</div>

