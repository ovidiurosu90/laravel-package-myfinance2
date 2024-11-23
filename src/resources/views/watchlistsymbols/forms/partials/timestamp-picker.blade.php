<div class="form-group required has-feedback row {{ $errors->has('timestamp') ? ' has-error ' : '' }}">
    <label for="timestamp" class="col-12 control-label">
        {{ trans('myfinance2::watchlistsymbols.forms.item-form.timestamp.label') }}
    </label>
    <div class="col-12">
        <!--
        <div class="input-group date" id="timestamp-picker" data-bs-target-input="nearest">
            <input name="timestamp" type="text" class="form-control datetimepicker-input" data-bs-target="#timestamp-picker"  value="{{ $timestamp }}" required />
            <div class="input-group-append" data-bs-target="#timestamp-picker" data-bs-toggle="datetimepicker">
                <div class="input-group-text"><i class="fa fa-calendar"></i></div>
            </div>
        </div>
         -->

        <div class="input-group date" id="timestamp-picker" data-td-target-input="nearest" data-td-target-toggle="nearest">
            <input name="timestamp" type="text" class="form-control datetimepicker-input" data-td-target="#timestamp-picker" value="{{ $timestamp }}" required />
            <span class="input-group-text" data-td-target="#timestamp-picker" data-td-toggle="datetimepicker" role="button">
                <span class="fas fa-calendar"></span>
            </span>
        </div>

    </div>
    @if ($errors->has('timestamp'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('timestamp') }}</strong>
            </span>
        </div>
    @endif
</div>

