<div class="mb-3 has-feedback row {{ $errors->has('description') ? 'has-error' : '' }}">
    <label for="description" class="col-4 control-label">
        {{ trans('myfinance2::orders.forms.item-form.description.label') }}
    </label>
    <div class="col-8 p-0 m-0 pt-1 pr-3 text-muted text-right small"
        id="fetched-description" style="display: none">
        <span></span>
    </div>
    <div class="col-12">
        <textarea id="description" name="description"
            class="form-control" rows="3"
            placeholder="{{ trans('myfinance2::orders.forms.item-form'
                . '.description.placeholder') }}"
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
