<div class="mb-3 required has-feedback row {{ $errors->has('symbol') ? 'has-error' : '' }}">
    <label for="symbol-select" class="col-12 control-label">
        {{ trans('myfinance2::orders.forms.item-form.symbol.label') }}
    </label>
    <div class="col-12 d-flex gap-2 align-items-start">
        <div class="flex-grow-1">
            <select name="symbol" id="symbol-select" required>
                <option value="">
                    {{ trans('myfinance2::orders.forms.item-form.symbol.placeholder') }}
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
        @if (empty($id))
        <button type="button" id="get-finance-data"
            class="btn btn-sm btn-outline-secondary mt-1"
            data-bs-toggle="tooltip" title="Get Finance Data">
            <span class="fas fa-sign-in" aria-hidden="true"></span>
        </button>
        @endif
    </div>
    @if ($errors->has('symbol'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('symbol') }}</strong>
            </span>
        </div>
    @endif
</div>
