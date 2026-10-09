<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

use ovidiuro\myfinance2\App\Models\PriceAlert;

/**
 * Validation shared by StoreAlert and UpdateAlert for the target mode of a price alert: a FIXED
 * price, or RELATIVE to a rolling closing high / low ("5% below the 52W high").
 *
 * The form sends the reference as one select ("HIGH:1y"); it is split into reference_type and
 * reference_window before validation. The submitted target_price of a RELATIVE alert is only the
 * preview: the controller replaces it with the server-side resolution, so it is optional here.
 */
trait ValidatesRelativeTarget
{
    /**
     * Split the combined reference select into its two columns.
     */
    protected function prepareForValidation(): void
    {
        $key = (string) $this->input('reference_key', '');
        if (str_contains($key, ':')) {
            [$type, $window] = explode(':', $key, 2);
            $this->merge(['reference_type' => $type, 'reference_window' => $window]);
        }
    }

    /**
     * The reference fields are only validated for a RELATIVE target, so a leftover value in the
     * hidden relative inputs can never block saving a FIXED alert.
     *
     * @return array<string, mixed>
     */
    protected function relativeTargetRules(): array
    {
        return [
            'target_price'     => 'required_unless:target_mode,RELATIVE|nullable|numeric',
            'target_mode'      => ['nullable', Rule::in(PriceAlert::TARGET_MODES)],
            'reference_type'   => ['exclude_unless:target_mode,RELATIVE', 'required',
                                   Rule::in(PriceAlert::REFERENCE_TYPES)],
            'reference_window' => ['exclude_unless:target_mode,RELATIVE', 'required',
                                   Rule::in(array_keys(PriceAlert::REFERENCE_WINDOW_DAYS))],
            'offset_pct'       => 'exclude_unless:target_mode,RELATIVE|required|numeric|min:0',
        ];
    }

    /**
     * Rules that depend on several fields: the offset bound per reference type, and no RELATIVE
     * target on an order alert (order alerts follow the order's limit price).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator)
        {
            if (!$this->isRelativeTarget()) {
                return;
            }

            $type = (string) $this->input('reference_type');
            $max  = PriceAlert::MAX_OFFSET_PCT[$type] ?? null;
            if ($max !== null && is_numeric($this->input('offset_pct'))
                && (float) $this->input('offset_pct') > $max) {
                $validator->errors()->add(
                    'offset_pct',
                    "The offset for a {$this->_referenceTypeLabel($type)} reference must be at most {$max}%."
                );
            }

            if ($this->alertSource() === 'order') {
                $validator->errors()->add(
                    'target_mode',
                    "Order alerts follow the order's limit price and cannot use a relative target."
                );
            }
        });
    }

    public function isRelativeTarget(): bool
    {
        return $this->input('target_mode') === 'RELATIVE';
    }

    /**
     * Target columns to save. A FIXED alert clears every reference column, so switching modes
     * leaves no stale reference behind; the resolved RELATIVE columns are set by the controller.
     *
     * @return array<string, mixed>
     */
    protected function relativeTargetFillData(): array
    {
        $relative = $this->isRelativeTarget();

        return [
            'target_mode'           => $relative ? 'RELATIVE' : 'FIXED',
            'reference_type'        => $relative ? $this->input('reference_type') : null,
            'reference_window'      => $relative ? $this->input('reference_window') : null,
            'offset_pct'            => $relative ? (float) $this->input('offset_pct') : null,
            'reference_price'       => null,
            'reference_date'        => null,
            'reference_resolved_at' => null,
        ];
    }

    /**
     * Source of the alert being validated ('manual', 'watchlist', 'order', ...).
     */
    abstract protected function alertSource(): ?string;

    private function _referenceTypeLabel(string $type): string
    {
        return $type === 'HIGH' ? 'high' : 'low';
    }
}
