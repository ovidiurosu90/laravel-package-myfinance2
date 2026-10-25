<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Models;

use Carbon\Carbon;

use ovidiuro\myfinance2\App\Services\OrderTierWarnings;
use ovidiuro\myfinance2\App\Services\TierReadingBuilder;

/**
 * The tier reading saved with an order (and a trade) at the moment of the decision, and the warnings it raised, so
 * "did this decision respect the tiers?" is answered from what was shown then, never from a reading recomputed later.
 * Rows are immutable: an update is refused; a changed decision inserts a new row and repoints the order, and the old
 * row is soft-deleted (a soft delete does not fire 'updating').
 */
class TierSnapshot extends MyFinance2Model
{
    public const SOURCE_LIVE    = 'live';
    public const SOURCE_REBUILT = 'rebuilt';
    public const SOURCE_NONE    = 'none';

    protected $table = 'tier_snapshots';

    protected $guarded = ['id'];

    protected $casts = [
        'id'              => 'integer',
        'user_id'         => 'integer',
        'as_of_date'      => 'date',
        'taken_at'        => 'datetime',
        'evaluated_at'    => 'datetime',
        'schema_version'  => 'integer',
        'acknowledged_at' => 'datetime',
        'reading'         => 'array',
        'warnings'        => 'array',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
        'deleted_at'      => 'datetime',
    ];

    protected $fillable = [
        'symbol',
        'action',
        'as_of_date',
        'taken_at',
        'evaluated_at',
        'source',
        'schema_version',
        'effective_tier',
        'verdict',
        'acknowledged_at',
        'reading',
        'warnings',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => false);
    }

    /**
     * A new row from a check (OrderTierCheck, RebuiltTierReadings): its reading, warnings and verdict; the serious
     * warnings marked confirmed when $confirmed (an order saved through the confirmation).
     */
    public static function createFromCheck(string $symbol, string $action, array $check, bool $confirmed): self
    {
        $now = Carbon::now();

        return self::create([
            'symbol'          => $symbol,
            'action'          => $action,
            'as_of_date'      => $check['as_of'] ?? $now->format('Y-m-d'),
            'taken_at'        => $check['taken_at'] ?? $now,
            'evaluated_at'    => $now,
            'source'          => $check['source'] ?? self::SOURCE_LIVE,
            'schema_version'  => TierReadingBuilder::SCHEMA_VERSION,
            'effective_tier'  => $check['reading']['effective_tier'] ?? null,
            'verdict'         => $check['verdict'],
            'acknowledged_at' => $confirmed && !empty(OrderTierWarnings::serious($check['warnings'])) ? $now : null,
            'reading'         => $check['reading'],
            'warnings'        => $check['warnings'],
        ]);
    }

    public function serious(): array
    {
        return OrderTierWarnings::serious($this->warnings ?? []);
    }

    public function soft(): array
    {
        return OrderTierWarnings::soft($this->warnings ?? []);
    }

    public function isLive(): bool
    {
        return $this->source === self::SOURCE_LIVE;
    }
}
