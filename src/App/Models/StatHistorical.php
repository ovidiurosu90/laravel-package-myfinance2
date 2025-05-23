<?php

namespace ovidiuro\myfinance2\App\Models;

use Thiagoprz\CompositeKey\HasCompositeKey;

class StatHistorical extends MyFinance2Model
{
    use HasCompositeKey;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stats_historical';

    /**
     * The primary key associated with the table.
     *
     * @var array
     */
    protected $primaryKey = ['date', 'symbol'];

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

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
        'unit_price' => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $fillable = [
        'date',
        'symbol',
        'unit_price',
        'currency_iso_code',
    ];

    public $incrementing = false;
}

