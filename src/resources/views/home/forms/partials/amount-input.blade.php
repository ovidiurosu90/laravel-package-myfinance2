<div class="mb-3 required has-feedback row">
    <label for="amount" class="col-12 control-label">
        {{ trans('myfinance2::ledger.forms.transaction-form.amount.label') }}
    </label>
    <div class="col-12">
        <input type="number" step=".01" id="amount" name="amount" class="form-control" value="" placeholder="{{ trans('myfinance2::ledger.forms.transaction-form.amount.placeholder') }}" required />
    </div>
</div>

