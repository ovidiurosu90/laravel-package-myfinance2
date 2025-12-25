<div class="mb-3 required has-feedback row {{ $errors->has('display_code') ?
                                                    'has-error' : '' }}">
    <label for="display_code" class="col-12 control-label">
        {{ trans('myfinance2::currencies.forms.item-form.display_code.label') }}
    </label>
    <div class="col-12">
        <input type="text" id="display_code-input" name="display_code"
               class="form-control" value="{{ $display_code }}"
               placeholder="{{ trans('myfinance2::currencies.forms.item-form.'
                                     . 'display_code.placeholder') }}"
               required maxlength="16" />
    </div>

    @if ($errors->has('display_code'))
    <div class="col-12">
        <span class="help-block">
            <strong>{{ $errors->first('display_code') }}</strong>
        </span>
    </div>
    @endif
</div>

