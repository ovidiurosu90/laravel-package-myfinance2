<div class="form-group required has-feedback row {{ $errors->has('description') ?
                                                    'has-error' : '' }}">
    <label for="description" class="col-12 control-label">
        {{ trans('myfinance2::accounts.forms.item-form.description.label') }}
    </label>
    <div class="col-12">
        <textarea id="description" name="description"
            class="form-control" rows="1"
            placeholder="{{ trans('myfinance2::accounts.forms.item-form.'
                                  . 'description.placeholder') }}"
            required maxlength="512">{{ $description }}</textarea>
    </div>

    @if ($errors->has('description'))
    <div class="col-12">
        <span class="help-block">
            <strong>{{ $errors->first('description') }}</strong>
        </span>
    </div>
    @endif
</div>

