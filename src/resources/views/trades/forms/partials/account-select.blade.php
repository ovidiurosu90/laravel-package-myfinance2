<div class="form-group required has-feedback row {{ $errors->has('account_id') ?
                                                    'has-error' : '' }}">
    <label for="account-select" class="col-12 control-label">
        {{ trans('myfinance2::trades.forms.item-form.account.label') }}
    </label>
    <div class="col-12">
        <select name="account_id" id="account-select" required>
            <option value="">{{ trans('myfinance2::trades.forms.item-form.'
                                      . 'account.placeholder') }}</option>
            @foreach ($accounts as $account)
            <option @if ($account_id == $account->id) selected @endif
                value="{{ $account->id }}">
                {{ $account->name }} ({!! $account->currency->display_code !!})
            </option>
            @endforeach
        </select>
    </div>
    @if ($errors->has('account_id'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('account_id') }}</strong>
            </span>
        </div>
    @endif
</div>

