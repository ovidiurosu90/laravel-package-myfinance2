<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Http\Request;

use ovidiuro\myfinance2\App\Models\SymbolTierOverride;
use ovidiuro\myfinance2\App\Services\CategorizationService;

class SymbolTierOverrideController extends MyFinance2Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Create or update a tier override for the authenticated user.
     */
    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'symbol'        => 'required|string|max:20',
            'tier_override' => 'required|string|in:PLATINUM,GOLD,SILVER,BRONZE,RUST',
            'note'          => 'nullable|string|max:500',
        ]);

        $userId = auth()->id();

        $override = SymbolTierOverride::withTrashed()
            ->where('user_id', $userId)
            ->where('symbol', $validated['symbol'])
            ->first();

        if ($override) {
            $override->restore();
            $override->update([
                'tier_override' => $validated['tier_override'],
                'note'          => $validated['note'] ?? null,
            ]);
        } else {
            $override = SymbolTierOverride::create([
                'symbol'        => $validated['symbol'],
                'tier_override' => $validated['tier_override'],
                'note'          => $validated['note'] ?? null,
            ]);
        }

        $this->_clearUserCaches($userId);

        return redirect()->back();
    }

    /**
     * Remove a tier override for the given symbol.
     */
    public function destroy(string $symbol): \Illuminate\Http\RedirectResponse
    {
        $userId   = auth()->id();
        $override = SymbolTierOverride::where('user_id', $userId)
            ->where('symbol', $symbol)
            ->first();

        if ($override) {
            $override->delete();
        }

        $this->_clearUserCaches($userId);

        return redirect()->back();
    }

    private function _clearUserCaches(int $userId): void
    {
        // Overrides only change the tier mapping, which lives in the categorization
        // cache; the next dashboard load rebuilds it from the still-warm performance
        // and drawdown snapshots, so the override takes effect immediately.
        CategorizationService::clearCache($userId);
    }
}
