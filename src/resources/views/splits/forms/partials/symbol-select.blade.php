<div class="mb-3 required has-feedback row {{ $errors->has('symbol') ? 'has-error' : '' }}">
    <label for="symbol-select" class="col-12 control-label">
        {{ trans('myfinance2::splits.forms.item-form.symbol.label') }}
    </label>
    <div class="col-12">
        <select name="symbol" id="symbol-select" required>
            <option value="">
                {{ trans('myfinance2::splits.forms.item-form.symbol.placeholder') }}
            </option>
            @foreach ($watchlistSymbols as $watchlistSymbol)
            <option @if ($symbol == $watchlistSymbol->symbol) selected @endif
                value="{{ $watchlistSymbol->symbol }}">
                {{ $watchlistSymbol->symbol }}
            </option>
            @endforeach
            @if (!empty($symbol) && !$watchlistSymbols->contains('symbol', $symbol))
            <option value="{{ $symbol }}" selected>{{ $symbol }}</option>
            @endif
        </select>
    </div>
    @if ($errors->has('symbol'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('symbol') }}</strong>
            </span>
        </div>
    @endif
</div>
