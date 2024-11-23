<div class="form-group required has-feedback row {{ $errors->has('amount') ? ' has-error ' : '' }}">
    <label for="amount" class="col-4 control-label">
        {{ trans('myfinance2::ledger.forms.transaction-form.amount.label') }}
    </label>
    <div class="col-8 p-0 m-0 text-muted" id="calculated-amount" style="display: none">
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

