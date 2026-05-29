<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Models;

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
}
