<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Http\Controllers;

use Illuminate\Http\Request;

use ovidiuro\myfinance2\App\Models\PortfolioPeakSetting;
use ovidiuro\myfinance2\App\Services\PortfolioPeakAlertService;

/**
 * Settings UI for the Portfolio Peak Alerts: the master enable, the daily-email toggle and the two
 * per-metric trigger toggles (EUR gain / return on cost).
 *
 * Off by default (no row = DISABLED): the daily email stays dark until the user enables the feature
 * and the email channel here. User-scoped; one row per user.
 */
class PortfolioPeakAlertController extends MyFinance2Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the settings page with the user's current configuration.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $userId  = (int) auth()->user()->id;
        $setting = PortfolioPeakSetting::firstOrNew(['user_id' => $userId]);

        // Live standing table (same computation the email uses), so the user can see how far each
        // window sits from its peak against its threshold right where the alert is configured.
        $preview = (new PortfolioPeakAlertService())->previewForUser($userId);

        return view('myfinance2::portfoliopeakalerts.crud.dashboard', [
            'setting' => $setting,
            'preview' => $preview,
        ]);
    }

    /**
     * Save the settings: master enable, email flag and the two per-metric toggles.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function save(Request $request)
    {
        $userId = (int) auth()->user()->id;

        PortfolioPeakSetting::updateOrCreate(
            ['user_id' => $userId],
            [
                'status'             => $request->boolean('enabled')
                    ? PortfolioPeakSetting::ENABLED
                    : PortfolioPeakSetting::DISABLED,
                'email_enabled'      => $request->boolean('email_enabled'),
                'change_eur_enabled' => $request->boolean('change_eur_enabled'),
                'change_pct_enabled' => $request->boolean('change_pct_enabled'),
            ]
        );

        return redirect()->route('myfinance2::portfolio-peak-alerts.index')
            ->with('success', 'Portfolio Peak Alert settings saved.');
    }
}
