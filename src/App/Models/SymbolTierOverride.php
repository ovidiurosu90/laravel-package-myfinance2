<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Models;

use ovidiuro\myfinance2\App\Services\LiveTierReadings;

class SymbolTierOverride extends MyFinance2Model
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
        'tier_override',
        'note',
    ];

    protected static function booted(): void
    {
        // A manual tier changes the tier in use the order form reads.
        $flush = static fn ($model) => LiveTierReadings::clearCache((int) ($model->user_id ?? 0));
        static::saved($flush);
        static::deleted($flush);
        static::restored($flush);
    }
}
