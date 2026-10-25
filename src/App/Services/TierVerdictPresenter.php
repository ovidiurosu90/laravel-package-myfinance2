<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\TierSnapshot;

/**
 * How a saved tier reading (TierSnapshot) reads in the order and trade lists: the verdict icon (no warning, soft
 * warnings only, against the tiers) and its tooltip listing the saved warnings. "No warning" is not "followed the
 * tiers": a SELL of a Gold symbol raises nothing, yet the tiers do not endorse it either.
 */
final class TierVerdictPresenter
{
    /** verdict => [icon, colour class, label] */
    private const ICONS = [
        OrderTierWarnings::VERDICT_OK      => ['fa-check', 'text-success', 'No warning'],
        OrderTierWarnings::VERDICT_SOFT    => ['fa-exclamation-circle', 'text-warning', 'Soft warnings'],
        OrderTierWarnings::VERDICT_AGAINST => ['fa-times', 'text-danger', 'Against the tiers'],
        OrderTierWarnings::VERDICT_NONE    => ['fa-minus', 'text-muted', 'No tier history'],
    ];

    /** Sort rank of a verdict, worst last. */
    private const RANK = [
        OrderTierWarnings::VERDICT_NONE    => 0,
        OrderTierWarnings::VERDICT_OK      => 1,
        OrderTierWarnings::VERDICT_SOFT    => 2,
        OrderTierWarnings::VERDICT_AGAINST => 3,
    ];

    /**
     * @return array{0: string, 1: string, 2: string} icon, colour class, label
     */
    public static function icon(string $verdict): array
    {
        return self::ICONS[$verdict] ?? self::ICONS[OrderTierWarnings::VERDICT_NONE];
    }

    /**
     * A sortable number: the verdict first, then the tier in use (weaker sorts later on an equal verdict).
     */
    public static function sortValue(TierSnapshot $snapshot): int
    {
        $tierRank = TierCalculationService::tierRank($snapshot->effective_tier);

        return (self::RANK[$snapshot->verdict] ?? 0) * 10 + ($tierRank === null ? 0 : 5 - $tierRank);
    }

    /**
     * The verdict tooltip (HTML, values escaped): the verdict with how many warnings and whether they were confirmed,
     * each saved warning, then today's tier when given and different.
     *
     * @param ?string $nowTier    today's tier in use (an open order), or null
     * @param array   $nowSerious today's serious warnings for the same order
     */
    public static function tooltip(TierSnapshot $snapshot, ?string $nowTier = null, array $nowSerious = []): string
    {
        $warnings = $snapshot->warnings ?? [];
        $serious  = OrderTierWarnings::serious($warnings);
        [, , $label] = self::icon($snapshot->verdict);

        $head = '<strong>' . e($label) . '</strong>';
        if (!empty($serious)) {
            $head .= ' (' . count($serious) . ' serious ' . (count($serious) === 1 ? 'warning' : 'warnings')
                . ($snapshot->acknowledged_at !== null
                    ? ', confirmed on save ' . $snapshot->acknowledged_at->format('Y-m-d H:i') : '')
                . ')';
        } elseif ($snapshot->verdict === OrderTierWarnings::VERDICT_OK) {
            $head .= ': the decision raised no tier warning (not the same as the tiers endorsing it).';
        } elseif ($snapshot->verdict === OrderTierWarnings::VERDICT_NONE) {
            $head .= ': ' . e($snapshot->reading['explanation'] ?? 'no stored reading for that date.');
        }
        $lines = [$head . (!empty($warnings) ? ':' : '')];
        foreach ($warnings as $warning) {
            $lines[] = e($warning['label'] . ': ' . $warning['text'])
                . ($warning['level'] === OrderTierWarnings::LEVEL_SOFT ? ' <span class="text-white-50">(soft)</span>'
                    : '');
        }
        if ($snapshot->source === TierSnapshot::SOURCE_REBUILT) {
            $lines[] = '<span class="text-white-50">Rebuilt from the stored tier history for '
                . e($snapshot->as_of_date?->format('Y-m-d') ?? '') . '; no override, superstar or portfolio share.'
                . '</span>';
        }
        if ($nowTier !== null && $nowTier !== $snapshot->effective_tier) {
            $lines[] = '<span class="text-warning">Now: ' . e(TierCalculationService::tierLabel($nowTier))
                . (empty($nowSerious) ? '; today\'s reading raises no serious warning.'
                    : '; today\'s reading would raise: '
                        . e(strtolower(implode(', ', array_column($nowSerious, 'label')))) . '.')
                . '</span>';
        }

        return implode('<br>', $lines);
    }
}
