<?php

namespace ovidiuro\myfinance2\App\Models;

class Currency extends MyFinance2Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'currencies';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'id',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'id'                   => 'integer',
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
        'deleted_at'           => 'datetime',
        'is_ledger_currency'   => 'boolean',
        'is_trade_currency'    => 'boolean',
        'is_dividend_currency' => 'boolean',
    ];

    protected $fillable = [
        'iso_code',
        'display_code',
        'name',
        'is_ledger_currency',
        'is_trade_currency',
        'is_dividend_currency',
    ];
}

