<div class="form-group required has-feedback row {{ $errors->has('fee') ?
                                                    'has-error' : '' }}">
    <label for="fee" class="col-12 control-label">
        {{ trans('myfinance2::trades.forms.item-form.fee.label') }}
        <span id="account_currency-label-tooltip" data-bs-toggle="tooltip"
            title="Account Currency">
            {!! !empty($accountModel) ? $accountModel->currency->display_code
                                      : '' !!}
        </span>
    </label>
    <div class="col-12">
        <input type="number" step=".01" id="fee" name="fee" class="form-control"
            value="{{ !empty($fee) ? $fee + 0 : '' }}"
            placeholder="{{ trans('myfinance2::trades.forms.item-form.fee.'
                                  . 'placeholder') }}" required />
    </div>
    @if ($errors->has('fee'))
        <div class="col-12">
            <span class="help-block">
                <strong>{{ $errors->first('fee') }}</strong>
            </span>
        </div>
    @endif
</div>

