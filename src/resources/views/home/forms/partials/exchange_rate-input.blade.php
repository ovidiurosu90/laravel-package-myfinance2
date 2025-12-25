<div class="mb-3 required has-feedback row">
    <label for="exchange_rate" class="col-12 control-label">
        {{ trans('myfinance2::ledger.forms.transaction-form.exchange_rate.label') }}
    </label>
    <div class="col-12">
        <input type="number" step=".0001" id="exchange_rate" name="exchange_rate" class="form-control" value="" placeholder="{{ trans('myfinance2::ledger.forms.transaction-form.exchange_rate.placeholder') }}" required />
    </div>
</div>

