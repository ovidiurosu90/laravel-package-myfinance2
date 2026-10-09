{{-- Target mode toggle: a fixed price, or relative to a rolling closing high / low. Order alerts
     follow the order's limit price, so the relative mode is disabled for source = order. --}}
@php
    $targetModeValue  = old('target_mode', $target_mode ?? 'FIXED');
    $relativeDisabled = ($source ?? 'manual') === 'order';
    if ($relativeDisabled) {
        $targetModeValue = 'FIXED';
    }
@endphp
<div class="mb-3 has-feedback row {{ $errors->has('target_mode') ? 'has-error' : '' }}">
    <label class="col-12 control-label">
        {{ trans('myfinance2::alerts.forms.item-form.target_mode.label') }}
    </label>
    <div class="col-12">
        <div class="btn-group w-100" role="group" aria-label="Target mode">
            @foreach ($targetModes as $value => $label)
                @php $isDisabled = $value === 'RELATIVE' && $relativeDisabled; @endphp
                <input type="radio" class="btn-check" name="target_mode" value="{{ $value }}"
                    id="target_mode-{{ strtolower($value) }}" autocomplete="off"
                    @if ($targetModeValue === $value) checked @endif
                    @if ($isDisabled) disabled @endif>
                <label class="btn btn-outline-secondary" for="target_mode-{{ strtolower($value) }}">
                    {{ $label }}
                </label>
            @endforeach
        </div>
        {{-- A disabled toggle cannot show a tooltip, so the reason is spelled out below it. --}}
        @if ($relativeDisabled)
            <div class="form-text">
                {{ trans('myfinance2::alerts.forms.item-form.target_mode.order-disabled') }}
            </div>
        @endif
    </div>
    @if ($errors->has('target_mode'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('target_mode') }}</strong>
            </span>
        </div>
    @endif
</div>
