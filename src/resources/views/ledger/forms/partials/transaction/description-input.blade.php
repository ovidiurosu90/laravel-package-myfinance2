<div class="form-group required has-feedback row {{ $errors->has('description') ? ' has-error ' : '' }}">
    <label for="description" class="col-12 control-label">
        {{ trans('myfinance2::ledger.forms.transaction-form.description.label') }}
    </label>
    <div class="col-12">
        <textarea id="description" name="description" class="form-control" placeholder="{{ trans('myfinance2::ledger.forms.transaction-form.description.placeholder') }}" required maxlength="127">{{ $description }}</textarea>
    </div>
    @if ($errors->has('description'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('description') }}</strong>
            </span>
        </div>
    @endif
</div>

