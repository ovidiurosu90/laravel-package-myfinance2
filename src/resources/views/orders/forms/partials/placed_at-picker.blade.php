<div class="mb-3 has-feedback row {{ $errors->has('placed_at') ? 'has-error' : '' }}">
    <label for="placed_at" class="col-12 control-label">
        {{ trans('myfinance2::orders.forms.item-form.placed_at.label') }}
    </label>
    <div class="col-12">
        <div class="input-group date" id="placed-at-picker"
            data-td-target-input="nearest" data-td-target-toggle="nearest">
            <input name="placed_at" type="text"
                class="form-control datetimepicker-input"
                data-td-target="#placed-at-picker"
                value="{{ !empty($placed_at) ? $placed_at : '' }}" />
            <span class="input-group-text" data-td-target="#placed-at-picker"
                data-td-toggle="datetimepicker" role="button">
                <span class="fas fa-calendar"></span>
            </span>
        </div>
    </div>
    @if ($errors->has('placed_at'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('placed_at') }}</strong>
            </span>
        </div>
    @endif
</div>
