<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Render-ready view model for the Dip Buying Plan.
 *
 * Single source of every display decision shared by the /positions panel, the standalone
 * current-episode card, the daily email body and the email subject: the pace verdict and its banner,
 * the "deploy more?" recommendation prose, the all-time-high suffix, the ladder rows (with their
 * gap, status and labels) and the three-basis regime rows. The blades and the Mailable only echo
 * these and map a status key to their own CSS (Bootstrap classes on the web, inline hex in email),
 * so the email can never show a different verdict, number or color than the panel.
 *
 * All resolvers are pure (no DB, no Laravel boot) and keyed off the plan / current-episode arrays
 * that DipBuyingPlanService and DipBuyingBacktestService already produce.
 */
final class DipBuyingPresenter
{
    /** Gap tolerance (percentage points of the pool) for the behind / ahead / on-plan verdict. */
    public const GAP_TOLERANCE_PCT = 1.0;

    /** A passed band counts as "behind" once the deployed gap exceeds this (percentage points). */
    private const PASSED_BEHIND_PCT = 0.05;

    /** The blue "local peak" accent (the fresh-pullback ruler), shared by every dip surface. */
    public const LOCAL_PEAK_COLOR = 'rgba(13,110,253,1)';

    /**
     * The full view model for the panel and the email body.
     *
     * @param array      $plan       DipBuyingPlanService plan array
     * @param array|null $current    in-progress current episode (report['current_episode']), or null
     * @param array|null $firstBand  first deploying band (report['first_band']), or null
     * @param array      $regime     three-basis regime breakdown (DipBuyingBacktestService::regimeSummary)
     *
     * @return array
     */
    public static function make(
        array $plan,
        ?array $current = null,
        ?array $firstBand = null,
        array $regime = []
    ): array
    {
        $effDd  = (float) ($plan['effective_dd_pct'] ?? 0);
        $colors = self::colors();

        $verdict = self::verdict($plan, $current);
        $ladder  = self::ladder($plan, $current);

        return [
            'effective_dd_pct' => $effDd,
            'dd_fmt'           => MoneyFormat::get_formatted_pct($effDd),
            'driver'           => ($plan['driver'] ?? 'vusa') === 'portfolio' ? 'your portfolio' : 'VUSA.AS',
            'vusa_dd_pct'      => isset($plan['vusa_dd_pct']) ? (float) $plan['vusa_dd_pct'] : null,
            'portfolio_dd_pct' => isset($plan['portfolio_dd_pct']) ? (float) $plan['portfolio_dd_pct'] : null,
            'trend'            => $plan['trend'] ?? [],
            'stall'            => $plan['stall'] ?? [],
            'colors'           => $colors,
            'verdict'          => $verdict,
            'recommendation'   => self::recommendation($current, $firstBand),
            'ath_suffix'       => self::athSuffix($current),
            'ladder'           => $ladder,
            'regime'           => self::regimeRows($regime),
            'summary'          => self::summary($plan, $current, $verdict),
        ];
    }

    /**
     * Chart line colors, the single source of truth shared with the chart, the panel and the email.
     *
     * @return array{effective: string, change: string, vusa: string, local: string}
     */
    public static function colors(): array
    {
        $metrics = DipBuyingBacktestService::chartMetrics();

        return [
            'effective' => $metrics['effective']['color'],
            'change'    => $metrics['changePercentage']['color'],
            'vusa'      => $metrics['vusa']['color'],
            'local'     => self::LOCAL_PEAK_COLOR,
        ];
    }

    /**
     * The pace verdict, its banner sentence and the email-subject headline. Computed on the
     * current-episode basis (cash at the dip's local peak, deployed since that peak), the same basis
     * the /positions card shows; falls back to the plan's own verdict when there is no active episode.
     *
     * @param array      $plan
     * @param array|null $current
     *
     * @return array{key: string, label: string, banner: string, headline: string, gap_pct: float, gap_eur: float}
     */
    public static function verdict(array $plan, ?array $current): array
    {
        $dd  = MoneyFormat::get_formatted_pct((float) ($plan['effective_dd_pct'] ?? 0));
        $num = fn ($v) => MoneyFormat::get_formatted_number_plain((float) $v, 0);

        if ($current !== null) {
            $ce       = self::ceVerdict($current);
            $key      = $ce['key'];
            $target   = $ce['target_pct'];
            $deployed = $ce['deployed_pct'];
            $gapEur   = $ce['gap_eur'];

            $banner = match ($key) {
                'no_dip'  => "Below the ladder's first band, nothing to deploy yet.",
                'behind'  => 'Behind the ladder at this depth: deploy about €' . $num($gapEur)
                    . ' more to reach target ' . (int) $target . '% (you are at ' . round($deployed, 1) . '%).',
                'ahead'   => 'Ahead of the ladder; hold the rest for a deeper leg ('
                    . round($deployed, 1) . '% vs ' . (int) $target . '% target).',
                default   => "Tracking the ladder's target at this depth ("
                    . round($deployed, 1) . '% vs ' . (int) $target . '%).',
            };
            $headline = match ($key) {
                'no_dip' => 'update at -' . $dd . '% drawdown (below the first band)',
                'behind' => 'behind plan at -' . $dd . '% drawdown (deploy ~EUR ' . $num($gapEur) . ')',
                'ahead'  => 'ahead of plan at -' . $dd . '% drawdown (hold dry powder)',
                default  => 'on plan at -' . $dd . '% drawdown',
            };

            return [
                'key'     => $key,
                'label'   => self::verdictLabel($key),
                'banner'  => $banner,
                'headline' => $headline,
                'gap_pct' => $ce['gap_pct'],
                'gap_eur' => $gapEur,
            ];
        }

        $key      = $plan['verdict'] ?? 'no_dip';
        $tranche  = (float) ($plan['suggested_tranche_eur'] ?? 0);
        $headline = match ($key) {
            'behind' => 'behind plan at -' . $dd . '% drawdown (deploy ~EUR ' . $num($tranche) . ')',
            'ahead'  => 'ahead of plan at -' . $dd . '% drawdown (hold dry powder)',
            default  => 'update at -' . $dd . '% drawdown',
        };

        return [
            'key'      => $key,
            'label'    => self::verdictLabel($key),
            'banner'   => (string) ($plan['verdict_message'] ?? ''),
            'headline' => $headline,
            'gap_pct'  => (float) ($plan['gap_pct'] ?? 0),
            'gap_eur'  => max(0.0, (float) ($plan['suggested_tranche_eur'] ?? 0)),
        ];
    }

    /**
     * Gap-to-target verdict on the current-episode basis alone (no plan needed): the basis the
     * /positions card and the email banner both read. Returns the verdict key with the resolved
     * pool / deployed / target / gap figures.
     *
     * @param array $ce  current episode
     *
     * @return array{key: string, target_pct: float, deployed_pct: float, deployed_eur: float, pool_eur: float, gap_pct: float, gap_eur: float}
     */
    public static function ceVerdict(array $ce): array
    {
        $target   = (float) $ce['guided']['target_pct'];
        $deployed = (float) $ce['actual']['deployed_pct'];
        $pool     = (float) $ce['pool_eur'];
        $gapPct   = $target - $deployed;
        $gapEur   = $gapPct / 100.0 * $pool;

        if ($target <= 0.0) {
            $key = 'no_dip';
        } elseif ($gapPct > self::GAP_TOLERANCE_PCT) {
            $key = 'behind';
        } elseif ($gapPct < -self::GAP_TOLERANCE_PCT) {
            $key = 'ahead';
        } else {
            $key = 'on_plan';
        }

        return [
            'key'          => $key,
            'label'        => self::verdictLabel($key),
            'target_pct'   => $target,
            'deployed_pct' => $deployed,
            'deployed_eur' => (float) $ce['actual']['deployed_eur'],
            'pool_eur'     => $pool,
            'gap_pct'      => $gapPct,
            'gap_eur'      => $gapEur,
        ];
    }

    /**
     * Human verdict label shared by the card badge and the email section heading.
     *
     * @param string $key
     *
     * @return string
     */
    public static function verdictLabel(string $key): string
    {
        return [
            'behind'  => 'Behind plan',
            'ahead'   => 'Ahead of plan',
            'on_plan' => 'On plan',
            'no_dip'  => 'Below the ladder',
        ][$key] ?? 'Update';
    }

    /**
     * The forward-looking "should I deploy more?" recommendation for the current episode, as shared
     * parts so each medium frames it (the card emphasizes the wait lead and wraps responsively; the
     * email uses the plain assembled text). Null when there is no active episode.
     *
     * @param array|null $ce
     * @param array|null $firstBand
     *
     * @return array{kind: string, text: string, lead: ?string, detail: ?string, first_band_sentence: ?string}|null
     */
    public static function recommendation(?array $ce, ?array $firstBand): ?array
    {
        if ($ce === null) {
            return null;
        }

        $num         = fn ($v) => MoneyFormat::get_formatted_number_plain((float) $v, 0);
        $target      = (float) $ce['guided']['target_pct'];
        $deployedPct = (float) $ce['actual']['deployed_pct'];
        $gapPct      = $target - $deployedPct;
        $gapEur      = $gapPct / 100.0 * (float) $ce['pool_eur'];

        // Raw stored values for interpolation, so the prose reads the same figures the cards show.
        $cd          = $ce['current_dd'];
        $targetRaw   = $ce['guided']['target_pct'];
        $deployedRaw = $ce['actual']['deployed_pct'];

        $lead = $detail = $firstBandSentence = null;

        if ($target <= 0.0) {
            $kind   = 'wait';
            $lead   = 'wait for a deeper leg';
            $detail = "At a {$cd}% drop, " . ($deployedPct > 0
                ? "you already deployed more than the ladder's first band guidance."
                : "the ladder's first band would not have you deploy yet.");
            if ($firstBand) {
                $firstBandSentence = "The first band only starts at a -{$firstBand['dd']}% drop, where it "
                    . "deploys {$firstBand['target']}% of the pool.";
            }
            $text = ucfirst($lead) . '. ' . $detail
                . ($firstBandSentence ? ' ' . $firstBandSentence : '');
        } elseif ($gapPct > self::GAP_TOLERANCE_PCT) {
            $kind = 'deploy_more';
            $text = 'To match the ladder at this depth you would deploy about €' . $num($gapEur)
                . " more (target {$targetRaw}% of pool, you are at {$deployedRaw}%).";
        } elseif ($gapPct < -self::GAP_TOLERANCE_PCT) {
            $kind = 'ahead';
            $text = "You have already deployed more than the ladder targets at this depth "
                . "({$deployedRaw}% vs {$targetRaw}%); keep the rest in reserve for a deeper leg.";
        } else {
            $kind = 'on_plan';
            $text = "You are tracking the ladder's target at this depth "
                . "({$deployedRaw}% vs {$targetRaw}%).";
        }

        return [
            'kind'                => $kind,
            'text'                => $text,
            'lead'                => $lead,
            'detail'              => $detail,
            'first_band_sentence' => $firstBandSentence,
        ];
    }

    /**
     * The "(label value on date)" suffix describing the all-time-high peak the running drawdown is
     * measured from, on the basis driving the effective drawdown (a VUSA.AS price in EUR, or the
     * portfolio's peak return as a percentage). Empty string when unavailable. HTML-safe: the values
     * are formatted numbers and a controlled label.
     *
     * @param array|null $ce
     *
     * @return string
     */
    public static function athSuffix(?array $ce): string
    {
        $ath = $ce['ath'] ?? null;
        if (empty($ath)) {
            return '';
        }

        $athValue = ($ath['unit'] ?? null) === 'pct'
            ? (((float) $ath['value'] >= 0 ? '+' : '-')
                . MoneyFormat::get_formatted_pct(abs((float) $ath['value'])) . '%')
            : '€' . MoneyFormat::get_formatted_number_plain((float) $ath['value'], 2);

        return ' (' . $ath['label'] . ' ' . $athValue . ' on ' . self::shortDate($ath['date']) . ')';
    }

    /**
     * The ladder rows resolved for display: each row's drawdown label, EUR target, gap (versus what
     * has been deployed this dip), status key/label/tooltip, plus the panel's collapse metadata. The
     * gap basis is the current episode when present (cash at the dip's local peak), else the plan,
     * so the table never contradicts the verdict.
     *
     * @param array      $plan
     * @param array|null $current
     *
     * @return array{rows: array<int, array>, collapse: bool, deployed_pct: float, deployed_eur: float, pool_eur: float}
     */
    public static function ladder(array $plan, ?array $current): array
    {
        $basis    = self::deployBasis($plan, $current);
        $deployed = $basis['deployed_pct'];
        $pool     = $basis['pool_eur'];
        $behind   = self::verdict($plan, $current)['key'] === 'behind';

        $ladder = $plan['ladder'] ?? [];

        // Collapse every band past the first few, but never collapse the band you are in now: if it
        // falls outside the visible rows, keep the whole ladder open so it is always on screen.
        $visibleLimit = 2;
        $currentIndex = null;
        foreach ($ladder as $i => $r) {
            if (($r['state'] ?? null) === 'current') {
                $currentIndex = $i;
                break;
            }
        }
        $collapse = count($ladder) > $visibleLimit
            && ($currentIndex === null || $currentIndex < $visibleLimit);

        $rows = [];
        foreach ($ladder as $i => $row) {
            $rows[] = self::ladderRow($row, $ladder, $i, $deployed, $pool, $behind, $collapse, $visibleLimit);
        }

        return [
            'rows'         => $rows,
            'collapse'     => $collapse,
            'deployed_pct' => $deployed,
            'deployed_eur' => $basis['deployed_eur'],
            'pool_eur'     => $pool,
        ];
    }

    /**
     * Resolve one ladder row (see ladder()).
     *
     * @param array $row
     * @param array $ladder
     * @param int   $index
     * @param float $deployedPct
     * @param float $poolEur
     * @param bool  $behind
     * @param bool  $collapse
     * @param int   $visibleLimit
     *
     * @return array
     */
    private static function ladderRow(
        array $row,
        array $ladder,
        int $index,
        float $deployedPct,
        float $poolEur,
        bool $behind,
        bool $collapse,
        int $visibleLimit
    ): array
    {
        $state     = $row['state'];
        $targetPct = (float) $row['target_pct'];
        $gapPct    = $targetPct - $deployedPct;

        // Passed bands (shallower than the current effective drawdown, including the zero-target
        // floor): the pace there is locked in. "behind" when deployment is under this band's target.
        $isPassed     = in_array($state, ['none', 'done'], true);
        $passedBehind = $isPassed && $gapPct > self::PASSED_BEHIND_PCT;

        if ($isPassed) {
            $statusKey   = $passedBehind ? 'passed_behind' : 'passed_ahead';
            $statusLabel = $passedBehind ? 'passed, behind' : 'passed, ahead';
            $statusTip   = 'This drawdown band is behind you now: you had ' . round($deployedPct, 1)
                . '% in versus its ' . (int) $targetPct . '% target, so you were '
                . ($passedBehind ? 'behind' : 'ahead of') . ' the pace at this depth and that cannot '
                . 'be changed. Deploying at the current band still moves the cumulative total.';
        } elseif ($state === 'current') {
            $statusKey   = $behind ? 'deploy_now' : 'current';
            $statusLabel = $behind ? 'deploy now' : 'current';
            $statusTip   = null;
        } elseif ($state === 'reserved') {
            $statusKey   = 'reserved';
            $statusLabel = 'reserved';
            $statusTip   = null;
        } else {
            $statusKey   = 'none';
            $statusLabel = '-';
            $statusTip   = null;
        }

        $ddLabel = $targetPct <= 0
            ? '<' . (int) ($ladder[$index + 1]['dd'] ?? $row['dd']) . '%'
            : (int) $row['dd'] . '%+';

        return [
            'state'        => $state,
            'dd_label'     => $ddLabel,
            'target_pct'   => $targetPct,
            'target_eur'   => $targetPct / 100.0 * $poolEur,
            'gap_pct'      => $gapPct,
            'gap_eur'      => $gapPct / 100.0 * $poolEur,
            'status_key'   => $statusKey,
            'status_label' => $statusLabel,
            'status_tip'   => $statusTip,
            'is_current'   => $state === 'current',
            'is_extra'     => $collapse && $index >= $visibleLimit,
        ];
    }

    /**
     * The three-basis regime rows (effective / change % / VUSA.AS), tinted with the chart colors and
     * with the "down now" tooltip prose resolved. Empty when no regime breakdown is available.
     *
     * @param array $regime
     *
     * @return array<string, array>
     */
    public static function regimeRows(array $regime): array
    {
        if (empty($regime)) {
            return [];
        }

        $colors = self::colors();
        $map    = ['effective' => 'effective', 'change' => 'change', 'vusa' => 'vusa'];

        $rows = [];
        foreach ($map as $basisKey => $colorKey) {
            $r = $regime[$basisKey] ?? null;
            if ($r === null) {
                continue;
            }

            $rows[$basisKey] = [
                'label'        => $r['label'],
                'color'        => $colors[$colorKey],
                'dd_pct'       => (float) $r['dd_pct'],
                'dd_fmt'       => self::ddFmt($r['dd_pct']),
                'down_now_pct' => (float) $r['current_drop_pct'],
                'down_now_fmt' => self::ddFmt($r['current_drop_pct']),
                'down_now_tip' => self::downNowTip($r),
                'is_effective' => $basisKey === 'effective',
            ];
        }

        return $rows;
    }

    /**
     * The collapsed-header summary on the /positions panel: the effective drawdown (with its accent
     * color), the local-peak drop, the status chip and the deployed/target read, all on the same
     * cash basis as the current-episode card.
     *
     * @param array      $plan
     * @param array|null $current
     * @param array      $verdict
     *
     * @return array
     */
    public static function summary(array $plan, ?array $current, array $verdict): array
    {
        $effDd  = (float) ($plan['effective_dd_pct'] ?? 0);
        $behind = $verdict['key'] === 'behind';

        $deployedPct = (float) ($current['actual']['deployed_pct'] ?? 0);
        $targetPct   = (float) ($current['guided']['target_pct'] ?? 0);

        if ($current === null) {
            $status = ['no dip', 'secondary', 'shield'];
        } elseif ($targetPct <= 0.0) {
            $status = ['wait for a deeper leg', 'secondary', 'shield'];
        } elseif ($behind) {
            $status = ['deploy more', 'danger', 'exclamation-triangle'];
        } else {
            $status = ['on track', 'success', 'check'];
        }

        return [
            'dd_fmt'       => MoneyFormat::get_formatted_pct($effDd),
            'dd_color'     => $effDd > 0 ? self::colors()['effective'] : 'inherit',
            'local_color'  => self::LOCAL_PEAK_COLOR,
            'local_dd'     => $current['current_dd'] ?? null,
            'status_label' => $status[0],
            'status_class' => $status[1],
            'status_icon'  => $status[2],
            'deployed_pct' => $deployedPct,
            'target_pct'   => $targetPct,
        ];
    }

    /**
     * The deployed / pool basis the ladder and verdict price against: the current episode (cash at
     * the dip's local peak) when active, else the plan's own pool.
     *
     * @param array      $plan
     * @param array|null $current
     *
     * @return array{deployed_pct: float, deployed_eur: float, pool_eur: float, target_pct: float}
     */
    public static function deployBasis(array $plan, ?array $current): array
    {
        if ($current !== null) {
            return [
                'deployed_pct' => (float) $current['actual']['deployed_pct'],
                'deployed_eur' => (float) $current['actual']['deployed_eur'],
                'pool_eur'     => (float) $current['pool_eur'],
                'target_pct'   => (float) $current['guided']['target_pct'],
            ];
        }

        return [
            'deployed_pct' => (float) ($plan['deployed_pct'] ?? 0),
            'deployed_eur' => (float) ($plan['deployed_eur'] ?? 0),
            'pool_eur'     => (float) ($plan['pool_amount_eur'] ?? 0),
            'target_pct'   => (float) ($plan['target_pct'] ?? 0),
        ];
    }

    /**
     * Drawdowns are stored as positive percentages: render with a leading minus, or a plain "0%" at
     * a fresh high.
     *
     * @param mixed $v
     *
     * @return string
     */
    public static function ddFmt($v): string
    {
        return ((float) $v) > 0.005
            ? '-' . MoneyFormat::get_formatted_pct((float) $v) . '%'
            : '0%';
    }

    /**
     * Short "d M 'y" date, shared by the cards and the email.
     *
     * @param string $date
     *
     * @return string
     */
    public static function shortDate(string $date): string
    {
        return Carbon::parse($date)->format("d M 'y");
    }

    /**
     * "N buys" / "1 sell" pluralized counter, shared by the cards and the email.
     *
     * @param mixed  $n
     * @param string $word
     *
     * @return string
     */
    public static function plural($n, string $word): string
    {
        return ((int) $n) . ' ' . Str::plural($word, (int) $n);
    }

    /**
     * Real-number explanation of a regime row's "Down now": it is measured from that basis's most
     * recent local peak, whose own depth (peak_dd) sets the floor the drop is taken from, so the
     * effective row can read smaller than Change % even though Drawdown never does.
     *
     * @param array $r  regime row
     *
     * @return string
     */
    public static function downNowTip(array $r): string
    {
        $pct    = fn ($v) => MoneyFormat::get_formatted_pct((float) $v) . '%';
        $peakDd = (float) ($r['peak_dd'] ?? 0);
        $floor  = $peakDd > 0.005
            ? 'sat ' . $pct($peakDd) . ' below its running peak'
            : 'was at a fresh high';
        $when   = !empty($r['since']) ? ' on ' . $r['since'] : '';

        return 'Measured from the most recent local peak of this basis, not its all-time peak. '
            . 'That peak' . $when . ' ' . $floor . '; it is ' . $pct($r['dd_pct'])
            . ' below now, so it is down ' . $pct($r['current_drop_pct']) . ' from that local high. '
            . 'Each row uses its own local peak, so Down now is not comparable between rows '
            . '(only Drawdown is).';
    }
}
