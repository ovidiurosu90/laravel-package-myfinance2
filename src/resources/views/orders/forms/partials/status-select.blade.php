<div class="mb-3 required has-feedback row {{ $errors->has('status') ? 'has-error' : '' }}">
    <label for="status-select" class="col-12 control-label">
        {{ trans('myfinance2::orders.forms.item-form.status.label') }}
    </label>
    <div class="col-12">
        <select name="status" id="status-select" required>
            <option value="">
                {{ trans('myfinance2::orders.forms.item-form.status.placeholder') }}
            </option>
            @foreach (['DRAFT' => 'Draft', 'PLACED' => 'Placed'] as $value => $label)
            <option @if ($status == $value) selected @endif value="{{ $value }}">
                {{ $label }}
            </option>
            @endforeach
        </select>
    </div>
    @if ($errors->has('status'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('status') }}</strong>
            </span>
        </div>
    @endif
</div>
