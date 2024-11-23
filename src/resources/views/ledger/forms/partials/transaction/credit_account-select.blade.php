<div class="form-group required has-feedback row {{ $errors->has('credit_account') ? ' has-error ' : '' }}">
    <label for="credit_account" class="col-12 control-label">
        {{ trans('myfinance2::ledger.forms.transaction-form.credit_account.label') }}
    </label>
    <div class="col-10">
        <select name="credit_account" id="transaction-credit_account-select" required>
            <option value="">{{ trans('myfinance2::ledger.forms.transaction-form.credit_account.placeholder') }}</option>
            @foreach ($creditAccounts as $creditAccountKey => $creditAccount)
                <option @if ($creditAccount == $credit_account) selected @endif value="{{ $creditAccountKey }}">
                    {{ $creditAccount }}
                </option>}
            @endforeach
        </select>
    </div>
    <div class="col-2 p-0 pt-1">
        <i class="btn p-0 m-0 fa fa-toggle-on" id="enable-transaction-credit_account-select" data-bs-toggle="tooltip" title="{{ trans('myfinance2::ledger.tooltips.enable-transaction-credit_account-select') }}" style="font-size: 24px;"></i>
    </div>
    @if ($errors->has('credit_account'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('credit_account') }}</strong>
            </span>
        </div>
    @endif
</div>

