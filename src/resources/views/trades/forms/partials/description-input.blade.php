<div class="mb-3 has-feedback row {{ $errors->has('description') ? ' has-error ' : '' }}">
    <label for="description" class="col-12 control-label">
        {{ trans('myfinance2::trades.forms.item-form.description.label') }}
    </label>
    <div class="col-12">
        <textarea id="description" name="description"
            class="form-control" rows="3"
            placeholder="{{ trans('myfinance2::trades.forms'
                . '.item-form.description.placeholder') }}"
            maxlength="512">{{ $description }}</textarea>
    </div>
    @if ($errors->has('description'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('description') }}</strong>
            </span>
        </div>
    @endif
</div>

