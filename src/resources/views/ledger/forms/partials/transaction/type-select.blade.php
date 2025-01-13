<div class="form-group required has-feedback row {{ $errors->has('type') ?
                                                    'has-error' : '' }}">
    <label for="type" class="col-12 control-label">
        {{ trans('myfinance2::ledger.forms.transaction-form.type.label') }}
    </label>
    <div class="col-12">
        <select name="type" id="transaction-type-select" required>
            <option value="">
                {{ trans('myfinance2::ledger.forms.transaction-form.type.'
                         . 'placeholder') }}
            </option>
            @foreach ($transactionTypes as $transactionTypeKey => $transactionType)
            <option @if ($transactionTypeKey == $type) selected @endif
                value="{{ $transactionTypeKey }}">
                {{ $transactionType }}
            </option>
            @endforeach
        </select>
    </div>
    @if ($errors->has('type'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('type') }}</strong>
            </span>
        </div>
    @endif
</div>

