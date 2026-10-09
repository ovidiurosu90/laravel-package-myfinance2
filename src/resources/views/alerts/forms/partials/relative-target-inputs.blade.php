{{-- Relative target, shown as a formula: [52W high] - [5] %. The select carries both the
     reference type and its window; the sign follows the type (a HIGH is only ever lowered, a LOW
     only ever raised), so the only "above / below" words on the form belong to the alert type.
     The plain-English summary of both settings lives in #relative-target-preview, filled by
     alerts/scripts/relative-target. The target price is re-resolved on save. --}}
@php
    $referenceKeyValue = old('reference_key', ($reference_type ?? 'HIGH') . ':' . ($reference_window ?? '1y'));
    $relativeErrors    = $errors->has('offset_pct') || $errors->has('reference_key')
        || $errors->has('reference_type') || $errors->has('reference_window');
    $referenceGroups   = ['HIGH' => 'Closing highs', 'LOW' => 'Closing lows'];
@endphp
<div id="relative-target-inputs" class="mb-3 has-feedback row {{ $relativeErrors ? 'has-error' : '' }}"
    @if (old('target_mode', $target_mode ?? 'FIXED') !== 'RELATIVE') style="display: none" @endif>
    <label for="reference_key-select" class="col-12 control-label">
        {{ trans('myfinance2::alerts.forms.item-form.relative_target.label') }}
    </label>
    <div class="col-12">
        <div class="input-group">
            <select name="reference_key" id="reference_key-select" class="form-select">
                @foreach ($referenceGroups as $type => $groupLabel)
                    <optgroup label="{{ $groupLabel }}">
                        @foreach ($referenceOptions as $value => $label)
                            @continue (!str_starts_with($value, $type . ':'))
                            <option value="{{ $value }}" @if ($referenceKeyValue === $value) selected @endif>
                                {{ $label }}
                            </option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <span class="input-group-text" id="relative-offset-sign"
                data-bs-toggle="tooltip"
                title="{{ trans('myfinance2::alerts.forms.item-form.relative_target.sign-tooltip') }}">
                {{ str_starts_with($referenceKeyValue, 'LOW:') ? '+' : '−' }}
            </span>
            <input type="number" step="any" min="0" name="offset_pct" id="offset_pct"
                class="form-control" style="max-width: 7rem"
                value="{{ old('offset_pct', $offset_pct ?? '5') }}">
            <span class="input-group-text">%</span>
        </div>
        <div id="relative-target-preview" class="form-text" style="display: none"></div>
    </div>
    @foreach (['offset_pct', 'reference_key', 'reference_type', 'reference_window'] as $errorField)
        @if ($errors->has($errorField))
            <div class="col-12">
                <span class="help-block">
                    <strong>{{ $errors->first($errorField) }}</strong>
                </span>
            </div>
        @endif
    @endforeach
</div>
