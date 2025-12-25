<div class="mb-3 required has-feedback row {{ $errors->has('iso_code') ?
                                                    'has-error' : '' }}">
    <label for="iso_code" class="col-12 control-label">
        {{ trans('myfinance2::currencies.forms.item-form.iso_code.label') }}
    </label>
    <div class="col-12">
        <input type="text" id="iso_code-input" name="iso_code"
               class="form-control" value="{{ $iso_code }}"
               placeholder="{{ trans('myfinance2::currencies.forms.item-form.'
                                     . 'iso_code.placeholder') }}"
               required maxlength="4" />
    </div>

    @if ($errors->has('iso_code'))
    <div class="col-12">
        <span class="help-block">
            <strong>{{ $errors->first('iso_code') }}</strong>
        </span>
    </div>
    @endif
</div>

