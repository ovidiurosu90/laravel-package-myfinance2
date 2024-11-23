<?php

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWatchlistSymbol extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (config('watchlistsymbols.guiCreateMiddlewareType') == 'role') {
            return $this->user()->hasRole(config('watchlistsymbols.guiCreateMiddleware'));
        }
        if (config('watchlistsymbols.guiCreateMiddlewareType') == 'permissions') {
            return $this->user()->hasPermission(config('watchlistsymbols.guiCreateMiddleware'));
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'timestamp'         => 'required|date_format:Y-m-d H:i:s',
            'symbol'            => 'required|string|max:16',
            'description'       => 'nullable|string|max:127',
        ];
    }

    /**
     * Return the fields and values to create a new transaction.
     *
     * @return array
     */
    public function fillData()
    {
        return [
            'timestamp'         => $this->timestamp,
            'symbol'            => $this->symbol,
            'description'       => $this->description,
        ];
    }
}

