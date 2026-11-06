{{-- Target mode toggle: a fixed price, or relative to a rolling closing high / low. --}}
@php
    $targetModeValue = old('target_mode', $target_mode ?? 'FIXED');
@endphp
<div class="mb-3 has-feedback row {{ $errors->has('target_mode') ? 'has-error' : '' }}">
    <label class="col-12 control-label">
        {{ trans('myfinance2::alerts.forms.item-form.target_mode.label') }}
    </label>
    <div class="col-12">
        <div class="btn-group w-100" role="group" aria-label="Target mode">
            {{-- Explicit end radii: the app's Bootstrap 4 CSS squares a .btn that is not the group's first
                 child, and each label follows its radio input. --}}
            @foreach ($targetModes as $value => $label)
                <input type="radio" class="btn-check" name="target_mode" value="{{ $value }}"
                    id="target_mode-{{ strtolower($value) }}" autocomplete="off"
                    @if ($targetModeValue === $value) checked @endif>
                <label for="target_mode-{{ strtolower($value) }}" @class([
                    'btn btn-outline-secondary',
                    'rounded-start' => $loop->first,
                    'rounded-end'   => $loop->last,
                ])>
                    {{ $label }}
                </label>
            @endforeach
        </div>
    </div>
    @if ($errors->has('target_mode'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('target_mode') }}</strong>
            </span>
        </div>
    @endif
</div>
