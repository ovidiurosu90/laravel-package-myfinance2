<div class="form-group has-feedback row {{ $errors->has('parent_id') ? ' has-error ' : '' }}">
    <label for="parent_id" class="col-12 control-label">
        {{ trans('myfinance2::ledger.forms.transaction-form.parent.label') }}
    </label>
    <div class="col-12">
        <select name="parent_id" id="transaction-parent-select">
            <option value="">{{ trans('myfinance2::ledger.forms.transaction-form.parent.placeholder') }}</option>
            @foreach ($rootTransactions as $rootTransaction)
                <option @if ($rootTransaction->id == $parent_id) selected @endif value="{{ $rootTransaction->id }}">
                    {{ $rootTransaction->type }} {{ number_format($rootTransaction->amount, 2) }}
                    {{ $rootTransaction->type == 'DEBIT' ? $rootTransaction->debit_currency : $rootTransaction->credit_currency }}
                    on {{ $rootTransaction->timestamp->format(trans('myfinance2::general.date-format')) }} with id {{ $rootTransaction->id }}
                </option>
            @endforeach
        </select>
    </div>
    @if ($errors->has('parent'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('parent') }}</strong>
            </span>
        </div>
    @endif
</div>

