<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;
use Illuminate\Mail\Mailable;
use ovidiuro\myfinance2\App\Models\PriceAlert;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;
use ovidiuro\myfinance2\App\Models\StockSplit;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\Mail\DipBuyingAlert;
use ovidiuro\myfinance2\Mail\PortfolioPeakAlert;
use ovidiuro\myfinance2\Mail\PriceAlertCreated;
use ovidiuro\myfinance2\Mail\PriceAlertStateChanged;
use ovidiuro\myfinance2\Mail\PriceAlertTriggered;
use ovidiuro\myfinance2\Mail\ReturnsMissingQuotes;
use ovidiuro\myfinance2\Mail\SplitApplied;

/**
 * One sample of every MyFinance2 email, for the host app to send and check how they look in a mail client. Built
 * from the user's real data where that is read-only (the price alert at the live price, today's peak-proximity
 * readings, the portfolio peak and dip buying figures), else from sample figures (the split, the returns warning).
 * Persists nothing: no alert fires and no notification, inbox or throttle row is written, so the real alert crons
 * are unaffected.
 *
 * The caller authenticates as the user (read-only, never saved) before building, so the user scope applies.
 */
class EmailSamples
{
    /**
     * The address the alert emails go to by default (MYFINANCE2_ALERTS_EMAIL_TO), if set.
     */
    public static function defaultRecipient(): ?string
    {
        return config('myfinance2.alerts.email_to') ?: null;
    }

    /**
     * Every sample, kind => a closure building its email (or emails), in the order they are sent. A closure returns
     * null (or an empty array) when no data reads that way today, and throws when the sample cannot be built.
     *
     * @return array<string, \Closure(): (Mailable|array<string, Mailable>|null)>
     */
    public function builders(int $userId): array
    {
        AssignedToUserScope::enable();
        $alert = fn (): PriceAlert => $this->_priceAlert();

        return [
            'price-alert'            => fn () => $this->_priceAlertTriggered($userId, $alert(), false),
            'price-alert-split'      => fn () => $this->_priceAlertTriggered($userId, $alert(), true),
            'alert-created'          => fn () => new PriceAlertCreated([$alert()], 'manual'),
            'alert-paused'           => fn () => new PriceAlertStateChanged([$alert()], 'paused'),
            'peak-proximity'         => fn () => (new PeakProximityAlertService())->sampleEmails(
                (new WatchlistSymbolsDashboard())->handle()['items']
            ),
            'portfolio-peak'         => fn () => $this->_portfolioPeak($userId),
            'dip-buying'             => fn () => $this->_dipBuying($userId),
            'split-applied'          => fn () => $this->_splitApplied($alert()),
            'returns-missing-quotes' => fn () => $this->_returnsMissingQuotes($alert()->symbol),
        ];
    }

    /**
     * The user's first active price alert, else the first one at all (read-only).
     */
    private function _priceAlert(): PriceAlert
    {
        $alert = PriceAlert::with('tradeCurrencyModel')->where('status', 'ACTIVE')->first()
            ?? PriceAlert::with('tradeCurrencyModel')->first();
        if ($alert === null) {
            throw new \RuntimeException('the user has no price alert to sample');
        }

        return $alert;
    }

    /**
     * The price alert email as the alert engine builds it, at the live price (or its split warning).
     */
    private function _priceAlertTriggered(int $userId, PriceAlert $alert, bool $isSplitWarning): PriceAlertTriggered
    {
        $quote = (new FinanceUtils())->getQuotes([$alert->symbol], null, false)[$alert->symbol] ?? null;
        $price = isset($quote['price']) ? (float) $quote['price'] : (float) $alert->target_price;
        $tier  = (new StoredTierReading())->forSymbol($userId, $alert->symbol);
        if ($isSplitWarning) {
            return new PriceAlertTriggered($alert, $price, null, true, $tier);
        }
        $position = (new AlertService())->emailPosition($userId, $alert->symbol, $quote ?? [], null);

        return new PriceAlertTriggered($alert, $price, $position, false, $tier);
    }

    /**
     * The portfolio peak email from the live breakdown; when no window is inside its threshold, the closest
     * trigger window stands in as the one that fires.
     */
    private function _portfolioPeak(int $userId): ?PortfolioPeakAlert
    {
        $service   = new PortfolioPeakAlertService();
        $preview   = $service->previewForUser($userId);
        $breakdown = $preview['breakdown'];
        $pairs     = array_values(array_filter($breakdown, fn (array $row): bool => !empty($row['triggered'])));
        if (empty($pairs)) {
            $candidates = array_filter(
                $breakdown,
                fn (array $row): bool => !empty($row['is_trigger']) && empty($row['skipped'])
                    && $row['proximity_pct'] !== null
            );
            if (empty($candidates)) {
                return null;
            }
            usort($candidates, fn (array $a, array $b): int => $b['proximity_pct'] <=> $a['proximity_pct']);
            $pairs = [array_merge($candidates[0], ['triggered' => true])];
        }

        return new PortfolioPeakAlert(
            $pairs, $preview['change_pct_current'], $preview['change_eur_current'], $service->vusaChangePct(),
            $breakdown
        );
    }

    /**
     * The dip buying email from the user's live plan, with the panel's detail; none without the feature enabled.
     */
    private function _dipBuying(int $userId): ?DipBuyingAlert
    {
        $plan = (new DipBuyingPlanService())->buildForUser($userId);
        if ($plan === null) {
            return null;
        }
        $detail = (new DipBuyingBacktestService())->panelDetail($userId, $plan);

        return new DipBuyingAlert($plan, 'band_deepened', $detail['regime'], $detail['current'], $detail['firstBand']);
    }

    /**
     * What a 2:1 split of the sampled alert's symbol would do to its open trades and the alert (computed, never
     * applied).
     */
    private function _splitApplied(PriceAlert $alert): SplitApplied
    {
        $split = new StockSplit([
            'symbol'            => $alert->symbol,
            'split_date'        => Carbon::today(),
            'ratio_numerator'   => 2,
            'ratio_denominator' => 1,
        ]);
        $trades = Trade::with('accountModel', 'tradeCurrencyModel')->where('symbol', $alert->symbol)
            ->where('status', 'OPEN')->orderBy('timestamp')->take(3)->get();

        return new SplitApplied($split, [
            'trades_updated'  => $trades->count(),
            'changed_trades'  => $trades->map(fn (Trade $trade): array => [
                'id'           => $trade->id,
                'account'      => $trade->accountModel?->name ?? '',
                'date'         => $trade->timestamp->format('Y-m-d'),
                'action'       => $trade->action,
                'old_quantity' => (float) $trade->quantity,
                'new_quantity' => (float) $trade->quantity * 2,
                'old_price'    => (float) $trade->unit_price,
                'new_price'    => (float) $trade->unit_price / 2,
                'currency'     => $trade->tradeCurrencyModel?->iso_code ?? '',
            ])->all(),
            'alerts_adjusted' => 1,
            'changed_alerts'  => [[
                'id'               => $alert->id,
                'alert_type'       => $alert->alert_type,
                'old_target_price' => (float) $alert->target_price,
                'new_target_price' => (float) $alert->target_price / 2,
                'currency'         => $alert->tradeCurrencyModel?->iso_code ?? '',
            ]],
        ]);
    }

    private function _returnsMissingQuotes(string $symbol): ReturnsMissingQuotes
    {
        $year = (int) date('Y') - 1;

        return new ReturnsMissingQuotes([$year => [[
            'date' => "{$year}-12-31", 'symbol' => $symbol, 'account_name' => 'Sample account',
            'quantity' => 12.5, 'delisted' => false,
        ]]]);
    }
}
