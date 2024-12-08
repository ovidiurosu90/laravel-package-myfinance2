<div class="form-group required has-feedback row {{ $errors->has('name') ?
                                                    'has-error' : '' }}">
    <label for="name" class="col-12 control-label">
        {{ trans('myfinance2::accounts.forms.item-form.name.label') }}
    </label>
    <div class="col-12">
        <input type="text" id="name-input" name="name"
               class="form-control" value="{{ $name }}"
               placeholder="{{ trans('myfinance2::accounts.forms.item-form.'
                                     . 'name.placeholder') }}"
               required maxlength="64" />
    </div>

    @if ($errors->has('name'))
    <div class="col-12">
        <span class="help-block">
            <strong>{{ $errors->first('name') }}</strong>
        </span>
    </div>
    @endif
</div>

