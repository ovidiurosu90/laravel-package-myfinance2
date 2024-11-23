<div class="form-group required has-feedback row {{ $errors->has('debit_account') ? ' has-error ' : '' }}">
    <label for="debit_account" class="col-12 control-label">
        {{ trans('myfinance2::ledger.forms.transaction-form.debit_account.label') }}
    </label>
    <div class="col-10">
        <select name="debit_account" id="transaction-debit_account-select" required>
            <option value="">{{ trans('myfinance2::ledger.forms.transaction-form.debit_account.placeholder') }}</option>
            @foreach ($debitAccounts as $debitAccountKey => $debitAccount)
                <option @if ($debitAccount == $debit_account) selected @endif value="{{ $debitAccountKey }}">
                    {{ $debitAccount }}
                </option>}
            @endforeach
        </select>
    </div>
    <div class="col-2 p-0 pt-1">
        <i class="btn p-0 m-0 fa fa-toggle-on" id="enable-transaction-debit_account-select" data-bs-toggle="tooltip" title="{{ trans('myfinance2::ledger.tooltips.enable-transaction-debit_account-select') }}" style="font-size: 24px;"></i>
    </div>
    @if ($errors->has('debit_account'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('debit_account') }}</strong>
            </span>
        </div>
    @endif
</div>

