<div class="mb-3 required has-feedback row">
    <label for="fee" class="col-12 control-label">
        {{ trans('myfinance2::ledger.forms.transaction-form.fee.label') }}
    </label>
    <div class="col-12">
        <input type="number" step=".01" id="fee" name="fee" class="form-control" value="" placeholder="{{ trans('myfinance2::ledger.forms.transaction-form.fee.placeholder') }}" required />
    </div>
</div>

