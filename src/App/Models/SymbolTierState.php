<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Models;

/**
 * The last settled computed tier per (user, symbol). This is the hysteresis memory: the
 * classifier reads it as the "previous tier" so a position on a tier line does not flip between
 * two tiers as its return wiggles. Written only by the cron (the single writer); the dashboard
 * read path consumes it but never persists.
 */
class SymbolTierState extends MyFinance2Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'id'         => 'integer',
        'user_id'    => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $fillable = [
        'symbol',
        'tier',
    ];
}
