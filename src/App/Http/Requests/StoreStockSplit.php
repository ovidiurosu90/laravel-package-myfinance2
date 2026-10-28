<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

use ovidiuro\myfinance2\App\Models\StockSplit;

class StoreStockSplit extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return Response
     */
    public function authorize(): Response
    {
        // The host app decides who manages this area (gate defined in its AuthServiceProvider).
        return Gate::inspect('myfinance2.manage', 'splits');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'symbol'            => 'required|string|max:16',
            'split_date'        => 'required|date|before_or_equal:today',
            'ratio_numerator'   => 'required|integer|min:2|max:255',
            'ratio_denominator' => 'required|integer|in:1',
            'notes'             => 'nullable|string',
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'ratio_denominator.in' => 'Only simple forward splits (denominator = 1) are supported.',
            'ratio_numerator.min'  => 'Ratio numerator must be at least 2 (forward splits only).',
        ];
    }

    /**
     * Return the fields and values to create a new stock split record.
     *
     * @return array
     */
    public function fillData(): array
    {
        return [
            'symbol'            => strtoupper(trim($this->symbol)),
            'split_date'        => $this->split_date,
            'ratio_numerator'   => (int) $this->ratio_numerator,
            'ratio_denominator' => (int) $this->ratio_denominator,
            'notes'             => $this->notes,
        ];
    }

    /**
     * Check if a split for this (user, symbol, split_date) already exists.
     *
     * @return bool
     */
    public function isDuplicate(): bool
    {
        return StockSplit::where('symbol', strtoupper(trim($this->symbol ?? '')))
            ->where('split_date', $this->split_date)
            ->exists();
    }
}
