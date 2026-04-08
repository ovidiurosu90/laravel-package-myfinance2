<div class="mb-3 required has-feedback row
    {{ $errors->has('ratio_numerator') || $errors->has('ratio_denominator') ? 'has-error' : '' }}">
    <label class="col-12 control-label">
        Split Ratio
        <span data-bs-toggle="tooltip"
              data-bs-placement="top"
              data-bs-html="true"
              data-bs-title="Forward split only (N:1, N &ge; 2).<br>Qty &times; N &nbsp;&middot;&nbsp; price &divide; N.<br>e.g. 25:1 &rarr; 1 share becomes 25 shares at 1/25 the price.">
            <i class="fa fa-fw fa-info-circle text-muted" aria-hidden="true"></i>
        </span>
    </label>
    <div class="col-12">
        <div class="input-group">
            <input type="number" name="ratio_numerator" id="ratio_numerator"
                class="form-control"
                required
                min="2"
                max="255"
                placeholder="e.g. 25"
                value="{{ old('ratio_numerator', $ratio_numerator ?? '') }}">
            <span class="input-group-text">:</span>
            <input type="number" name="ratio_denominator" id="ratio_denominator"
                class="form-control"
                style="max-width: 64px;"
                value="{{ old('ratio_denominator', $ratio_denominator ?? 1) }}"
                readonly
                tabindex="-1">
        </div>
        @if ($errors->has('ratio_numerator'))
            <span class="help-block">
                <strong>{{ $errors->first('ratio_numerator') }}</strong>
            </span>
        @endif
        @if ($errors->has('ratio_denominator'))
            <span class="help-block">
                <strong>{{ $errors->first('ratio_denominator') }}</strong>
            </span>
        @endif
    </div>
</div>
