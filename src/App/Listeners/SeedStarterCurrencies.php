<?php

namespace ovidiuro\myfinance2\App\Listeners;

use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Gate;

use ovidiuro\myfinance2\App\Models\Currency;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

/**
 * Gives a newly verified user the starter currencies (UNK, USD, EUR), so the forms have something to pick from on the
 * first visit. The host app fires Laravel's Verified event when it activates an account (README, "Onboarding"); users
 * that cannot open MyFinance2 (myfinance2.access) or already have currencies are left alone.
 */
class SeedStarterCurrencies
{
    public function handle(Verified $event): void
    {
        $user = $event->user;
        if (empty($user->id) || Gate::forUser($user)->denies('myfinance2.access')) {
            return;
        }

        // No logged-in user is guaranteed here, so filter by the verified user explicitly
        $hasCurrencies = Currency::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $user->id)
            ->exists();
        if ($hasCurrencies) {
            return;
        }

        // insert() skips the model's creating hook, which would take the user from auth()
        Currency::withoutGlobalScope(AssignedToUserScope::class)->insert([
            [
                'user_id'              => $user->id,
                'iso_code'             => 'UNK',
                'display_code'         => '&curren;',
                'name'                 => 'Unknown',
                'is_ledger_currency'   => false,
                'is_trade_currency'    => false,
                'is_dividend_currency' => false,
            ],
            [
                'user_id'              => $user->id,
                'iso_code'             => 'USD',
                'display_code'         => '&dollar;',
                'name'                 => 'US Dollar',
                'is_ledger_currency'   => true,
                'is_trade_currency'    => true,
                'is_dividend_currency' => true,
            ],
            [
                'user_id'              => $user->id,
                'iso_code'             => 'EUR',
                'display_code'         => '&euro;',
                'name'                 => 'Euro',
                'is_ledger_currency'   => true,
                'is_trade_currency'    => true,
                'is_dividend_currency' => true,
            ],
        ]);
    }
}
