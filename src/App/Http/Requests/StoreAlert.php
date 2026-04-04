<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use ovidiuro\myfinance2\App\Models\Currency;

class StoreAlert extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        if (config('alerts.guiCreateMiddlewareType') == 'role') {
            return $this->user()->hasRole(config('alerts.guiCreateMiddleware'));
        }

        if (config('alerts.guiCreateMiddlewareType') == 'permissions') {
            return $this->user()->hasPermission(config('alerts.guiCreateMiddleware'));
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $dbConnection = config('myfinance2.db_connection');
        $currenciesTableName = $dbConnection . '.' . (new Currency())->getTable();

        return [
            'symbol'               => 'required|string|max:16',
            'alert_type'           => ['required', Rule::in(['PRICE_ABOVE', 'PRICE_BELOW'])],
            'target_price'         => 'required|numeric',
            'trade_currency_id'    => 'nullable|integer|exists:' . $currenciesTableName . ',id',
            'status'               => ['nullable', Rule::in(['ACTIVE', 'PAUSED'])],
            'source'               => 'nullable|string|max:50',
            'notification_channel' => 'nullable|string|max:20',
            'notes'                => 'nullable|string',
            'expires_at'           => 'nullable|date',
        ];
    }

    /**
     * Return the fields and values to create a new alert.
     *
     * @return array
     */
    public function fillData(): array
    {
        return [
            'symbol'               => $this->symbol,
            'alert_type'           => $this->alert_type,
            'target_price'         => $this->target_price,
            'trade_currency_id'    => $this->trade_currency_id,
            'status'               => $this->status ?? 'ACTIVE',
            'source'               => $this->source ?? 'manual',
            'notification_channel' => $this->notification_channel ?? 'email',
            'notes'                => $this->notes,
            'expires_at'           => $this->expires_at,
        ];
    }
}
