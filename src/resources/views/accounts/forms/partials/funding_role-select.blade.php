<div class="mb-3 has-feedback row {{
    $errors->has('funding_role') ? 'has-error' : '' }}">

    <label for="funding_role-select" class="col-12 control-label">
        {{ trans('myfinance2::accounts.forms.item-form.'
                 . 'funding_role.label') }}
    </label>

    <div class="col-12">
        <select class="form-select" name="funding_role"
                id="funding_role-select">
            <option value=""
                @if (empty($funding_role)) selected @endif>
            </option>
            @foreach(\ovidiuro\myfinance2\App\Enums\FundingRole::cases()
                     as $role)
                <option value="{{ $role->value }}"
                    @if (($funding_role ?? '') instanceof \ovidiuro\myfinance2\App\Enums\FundingRole
                        ? $funding_role === $role
                        : ($funding_role ?? '') === $role->value)
                        selected
                    @endif>
                    {{ trans('myfinance2::accounts.forms.item-form.'
                             . 'funding_role.options.'
                             . $role->value) }}
                </option>
            @endforeach
        </select>
    </div>

    @if ($errors->has('funding_role'))
    <div class="col-12">
        <span class="help-block">
            <strong>{{ $errors->first('funding_role') }}</strong>
        </span>
    </div>
    @endif
</div>

