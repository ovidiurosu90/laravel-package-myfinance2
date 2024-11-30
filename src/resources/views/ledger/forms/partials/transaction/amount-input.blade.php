<div class="form-group required has-feedback row {{ $errors->has('amount') ? ' has-error ' : '' }}">
    <label for="amount" class="col-5 control-label">
        {{ trans('myfinance2::ledger.forms.transaction-form.amount.label') }}
        <span id="amount_currency-label-tooltip" data-bs-toggle="tooltip" title="Amount Currency">
        @if ($type && $type == 'DEBIT' && $debit_currency)
            {!! config('general.currencies_display.' . $debit_currency) !!}
        @elseif ($type && $type == 'CREDIT' && $credit_currency)
            {!! config('general.currencies_display.' . $credit_currency) !!}
        @endif
        </span>
    </label>
    <div class="col-7 p-0 m-0 text-muted" id="calculated-amount" style="display: none">
        Calculated: <span></span>
    </div>
    <div class="col-12">
        <input type="number" step=".01" id="amount" name="amount" class="form-control" value="{{ $amount }}" placeholder="{{ trans('myfinance2::ledger.forms.transaction-form.amount.placeholder') }}" required />
    </div>
    @if ($errors->has('amount'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('amount') }}</strong>
            </span>
        </div>
    @endif
</div>

