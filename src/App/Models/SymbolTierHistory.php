<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Models;

/**
 * One row per (user, symbol, trading day): the tier basis, its value and the raw tier (no hysteresis,
 * no manual override) the classifier would have produced on that day. Derived data, regenerable with
 * finance:tier-history-backfill, so it is not part of the backup. Written by the backfill command and
 * the hourly cron through the query builder (no authenticated user there).
 */
class SymbolTierHistory extends MyFinance2Model
{
    protected $table = 'symbol_tier_history';

    protected $guarded = ['id'];

    protected $casts = [
        'id'          => 'integer',
        'user_id'     => 'integer',
        'date'        => 'date',
        'basis_value' => 'float',
        'market_value' => 'float',
        'own_value'   => 'float',
        'holding_value' => 'float',
        'holding_since' => 'date',
        'is_owned'    => 'boolean',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
        'deleted_at'  => 'datetime',
    ];

    protected $fillable = [
        'symbol',
        'date',
        'basis',
        'basis_value',
        'market_value',
        'own_value',
        'holding_value',
        'holding_since',
        'tier',
        'is_owned',
        'source',
    ];
}
