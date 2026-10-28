<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

use ovidiuro\myfinance2\App\Http\Requests\Concerns\ValidatesRelativeTarget;
use ovidiuro\myfinance2\App\Models\Currency;
use ovidiuro\myfinance2\App\Models\PriceAlert;
use ovidiuro\myfinance2\App\Rules\OwnRecord;

class UpdateAlert extends FormRequest
{
    use ValidatesRelativeTarget;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return Response
     */
    public function authorize(): Response
    {
        // The host app decides who manages this area (gate defined in its AuthServiceProvider).
        return Gate::inspect('myfinance2.manage', 'alerts');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return array_merge([
            'symbol'               => 'required|string|max:16',
            'alert_type'           => ['required', Rule::in(['PRICE_ABOVE', 'PRICE_BELOW'])],
            'trade_currency_id'    => ['nullable', 'integer', OwnRecord::of(Currency::class)],
            'status'               => ['nullable', Rule::in(['ACTIVE', 'PAUSED'])],
            'notes'                => 'nullable|string',
            'expires_at'           => 'nullable|date',
        ], $this->relativeTargetRules());
    }

    /**
     * Return the fields and values to update an alert.
     *
     * @param int $id
     *
     * @return array
     */
    public function fillData(int $id): array
    {
        return array_merge([
            'symbol'            => $this->symbol,
            'alert_type'        => $this->alert_type,
            'target_price'      => $this->target_price,
            'trade_currency_id' => $this->trade_currency_id,
            'status'            => $this->status ?? 'ACTIVE',
            'notes'             => $this->notes,
            'expires_at'        => $this->expires_at,
        ], $this->relativeTargetFillData());
    }

    /**
     * The source is not editable, so it comes from the stored alert.
     */
    protected function alertSource(): ?string
    {
        $id = $this->route('price_alert');

        return $id !== null ? PriceAlert::find((int) $id)?->source : null;
    }
}
