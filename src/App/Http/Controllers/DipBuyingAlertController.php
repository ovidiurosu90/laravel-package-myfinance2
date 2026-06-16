<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use ovidiuro\myfinance2\App\Models\DipBuyingSetting;
use ovidiuro\myfinance2\App\Services\DipBuyingBacktestService;
use ovidiuro\myfinance2\App\Services\DipBuyingPlanService;

/**
 * Settings UI for the Dip Buying Plan: the single EUR pool, the master enable, the daily-email
 * toggle and an optional advanced override of the reserve ladder.
 *
 * Off by default (no row = DISABLED): the /positions panel and the daily email stay dark until the
 * user sets a pool and enables the feature here. User-scoped; one row per user.
 */
class DipBuyingAlertController extends MyFinance2Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the settings page with the user's current configuration and the resolved ladder, plus the
     * standalone drawdown chart context (optional ?min_drop= controls the drop threshold).
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $userId  = (int) auth()->user()->id;
        $setting = DipBuyingSetting::firstOrNew(['user_id' => $userId]);

        $engine = new DipBuyingPlanService();
        $bands  = $engine->resolveBands($setting->bands);

        $minDrop  = $request->query('min_drop');
        $mode     = $request->query('drop_mode');
        $dipChart = (new DipBuyingBacktestService())->chartContext(
            $userId,
            null,
            $minDrop !== null && is_numeric($minDrop) ? (float) $minDrop : null,
            $mode ?: null
        );

        // Suggest the pool from the current total cash across accounts (EUR), the same figure the
        // /positions user overview shows, so a first-time user starts from a sensible number.
        $currentCashEur = $engine->currentCashEur($userId);
        $savedPool      = (float) $setting->pool_amount_eur;
        $suggestedPool  = $savedPool > 0.0
            ? $savedPool
            : ($currentCashEur !== null ? round($currentCashEur, 2) : 0.0);

        return view('myfinance2::dipbuyingalerts.crud.dashboard', [
            'setting'        => $setting,
            'bands'          => $bands,
            'usingDefault'   => empty($setting->bands),
            'currentCashEur' => $currentCashEur,
            'suggestedPool'  => $suggestedPool,
            'dipChart'       => $dipChart,
        ]);
    }

    /**
     * Save the settings: pool amount, enabled flag, email flag and optional bands JSON override.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function save(Request $request)
    {
        $userId = (int) auth()->user()->id;

        $validator = Validator::make($request->all(), [
            'pool_amount_eur' => 'required|numeric|min:0',
            'bands'           => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->_redirect()->withErrors($validator)
                ->with('error', 'Could not save: check the pool amount and the ladder JSON.');
        }

        $bands = $this->_parseBands($request->input('bands'));
        if ($bands === false) {
            return $this->_redirect()->with('error',
                'Invalid ladder JSON. Use an array of {"dd": number, "target": number} entries, or leave it blank for the default.');
        }

        DipBuyingSetting::updateOrCreate(
            ['user_id' => $userId],
            [
                'pool_amount_eur' => (float) $request->input('pool_amount_eur'),
                'status'          => $request->boolean('enabled')
                    ? DipBuyingSetting::ENABLED
                    : DipBuyingSetting::DISABLED,
                'email_enabled'   => $request->boolean('email_enabled'),
                'bands'           => $bands,
            ]
        );

        DipBuyingPlanService::clearCache($userId);

        return $this->_redirect()->with('success', 'Dip Buying Plan settings saved.');
    }

    /**
     * Validate and decode the optional bands JSON override. Returns the normalized array, null when
     * blank (use the default), or false when invalid.
     *
     * @param string|null $raw
     *
     * @return array|null|false
     */
    private function _parseBands(?string $raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded)) {
            return false;
        }

        $bands = [];
        foreach ($decoded as $band) {
            if (!isset($band['dd'], $band['target'])
                || !is_numeric($band['dd']) || !is_numeric($band['target'])
                || $band['dd'] < 0 || $band['target'] < 0 || $band['target'] > 100
            ) {
                return false;
            }
            $bands[] = ['dd' => (float) $band['dd'], 'target' => (float) $band['target']];
        }

        return $bands;
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    private function _redirect()
    {
        return redirect()->route('myfinance2::dip-buying-alerts.index');
    }
}
