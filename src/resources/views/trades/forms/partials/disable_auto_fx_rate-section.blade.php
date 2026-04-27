<div class="row">
    <div class="col-12 col-md-3">
        <div class="mb-3">
            <label class="control-label d-block mb-1">
                {{ trans('myfinance2::trades.forms.item-form.disable_auto_fx_rate.label') }}
                <i class="fa fa-info-circle ms-1 text-muted" data-bs-toggle="tooltip"
                    data-bs-placement="top"
                    title="{{ trans('myfinance2::trades.forms.item-form.disable_auto_fx_rate.help') }}">
                </i>
            </label>
            {{-- Use d-flex to bypass form-switch float/negative-margin layout --}}
            <div class="d-flex align-items-center gap-2 form-check form-switch"
                style="padding-left: 0;">
                <input class="form-check-input mt-0 ms-0" type="checkbox" role="switch"
                    name="disable_auto_fx_rate" id="disable-auto-fx-checkbox" value="1"
                    @if (!empty($disable_auto_fx_rate)) checked @endif>
                <label class="form-check-label mb-0" for="disable-auto-fx-checkbox">
                    <span id="auto-fx-toggle-label">
                        @if (!empty($disable_auto_fx_rate)) On @else Off @endif
                    </span>
                </label>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-9" id="paired-account-wrapper"
        @if (empty($disable_auto_fx_rate)) style="display:none;" @endif>
        <div class="mb-3 {{ $errors->has('paired_account_id') ? 'has-error' : '' }}">
            <label for="paired-account-select" class="control-label d-block mb-1">
                {{ trans('myfinance2::trades.forms.item-form.paired_account.label') }}
                <i class="fa fa-info-circle ms-1 text-muted" data-bs-toggle="tooltip"
                    data-bs-placement="top"
                    title="{{ trans('myfinance2::trades.forms.item-form.paired_account.help') }}">
                </i>
            </label>
            <select name="paired_account_id" id="paired-account-select">
                <option value="">
                    {{ trans('myfinance2::trades.forms.item-form.paired_account.placeholder') }}
                </option>
                @foreach ($ledgerAccounts as $ledgerAccount)
                <option
                    @if (!empty($paired_account_id) && $paired_account_id == $ledgerAccount->id)
                        selected
                    @endif
                    value="{{ $ledgerAccount->id }}"
                    data-name="{{ $ledgerAccount->name }}"
                    data-currency="{{ $ledgerAccount->currency->iso_code }}">
                    {{ $ledgerAccount->name }} ({!! $ledgerAccount->currency->display_code !!})
                </option>
                @endforeach
            </select>
            @if ($errors->has('paired_account_id'))
                <div class="text-danger small mt-1">
                    <strong>{{ $errors->first('paired_account_id') }}</strong>
                </div>
            @endif
        </div>
    </div>
</div>
