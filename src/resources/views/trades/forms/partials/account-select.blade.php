<div class="form-group required has-feedback row {{ $errors->has('account') ? ' has-error ' : '' }}">
    <label for="account" class="col-12 control-label">
        {{ trans('myfinance2::trades.forms.item-form.account.label') }}
    </label>
    <div class="col-12">
        <select name="account" id="account-select" required>
            <option value="">{{ trans('myfinance2::trades.forms.item-form.account.placeholder') }}</option>
            @foreach ($accounts as $accountKey => $accountValue)
                <option @if ($accountKey == $account) selected @endif value="{{ $accountKey }}">
                    {{ $accountValue }}
                </option>}
            @endforeach
        </select>
    </div>
    @if ($errors->has('account'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('account') }}</strong>
            </span>
        </div>
    @endif
</div>

