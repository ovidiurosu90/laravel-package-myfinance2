<div class="form-group required has-feedback row {{ $errors->has('fee') ? ' has-error ' : '' }}">
    <label for="fee" class="col-12 control-label">
        {{ trans('myfinance2::ledger.forms.transaction-form.fee.label') }}
    </label>
    <div class="col-12">
        <input type="number" step=".01" id="fee" name="fee" class="form-control" value="{{ $fee }}" placeholder="{{ trans('myfinance2::ledger.forms.transaction-form.fee.placeholder') }}" required />
    </div>
    @if ($errors->has('fee'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('fee') }}</strong>
            </span>
        </div>
    @endif
</div>

