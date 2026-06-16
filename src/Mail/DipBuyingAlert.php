<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Route;

use ovidiuro\myfinance2\App\Services\DipBuyingPresenter;
use ovidiuro\myfinance2\Mail\Concerns\HasAppLabel;

/**
 * The opt-in daily Dip Buying Plan email. Fires only on a state change (band deepened, crossed to
 * behind plan, or the stall backstop activated), one per day.
 */
class DipBuyingAlert extends Mailable
{
    use Queueable, SerializesModels, HasAppLabel;

    private const BENCHMARK_SYMBOL = 'VUSA.AS';
    private const YAHOO_QUOTE_URL  = 'https://finance.yahoo.com/quote/';

    private array $_plan;
    private string $_trigger;
    private array $_regime;
    private ?array $_current;
    private ?array $_firstBand;

    /**
     * @param array      $plan       the DipBuyingPlanService plan array
     * @param string     $trigger    band_deepened | crossed_behind | stall
     * @param array      $regime     three-basis regime breakdown (DipBuyingBacktestService::regimeSummary)
     * @param array|null $current    the in-progress current episode (report['current_episode']), or null
     * @param array|null $firstBand  the first deploying band (report['first_band']), or null
     */
    public function __construct(
        array $plan,
        string $trigger,
        array $regime = [],
        ?array $current = null,
        ?array $firstBand = null
    )
    {
        $this->_plan      = $plan;
        $this->_trigger   = $trigger;
        $this->_regime    = $regime;
        $this->_current   = $current;
        $this->_firstBand = $firstBand;
    }

    /**
     * @return $this
     */
    public function build()
    {
        $label = $this->_appLabel();

        // One render-ready view model shared with the /positions panel, so the email can never show a
        // different verdict, number or color than the panel. The subject reuses its resolved headline.
        $present  = DipBuyingPresenter::make(
            $this->_plan, $this->_current, $this->_firstBand, $this->_regime
        );
        $subject  = "{$label} Dip Buying Plan: {$present['verdict']['headline']}";

        $panelUrl = Route::has('myfinance2::positions')
            ? route('myfinance2::positions')
            : url('/positions');
        $settingsUrl = Route::has('myfinance2::dip-buying-alerts.index')
            ? route('myfinance2::dip-buying-alerts.index')
            : url('/dip-buying-alerts');

        return $this->subject($subject)
            ->view('myfinance2::emails.dip-buying-alert')
            ->with([
                'present'     => $present,
                'plan'        => $this->_plan,
                'trigger'     => $this->_trigger,
                'regime'      => $this->_regime,
                'current'     => $this->_current,
                'firstBand'   => $this->_firstBand,
                'panelUrl'    => $panelUrl,
                'settingsUrl' => $settingsUrl,
                'vusaUrl'     => self::YAHOO_QUOTE_URL . self::BENCHMARK_SYMBOL,
            ]);
    }
}
