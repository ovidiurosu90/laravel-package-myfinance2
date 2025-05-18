<?php

namespace ovidiuro\myfinance2\App\Models;

use Thiagoprz\CompositeKey\HasCompositeKey;

class StatToday extends MyFinance2Model
{
    use HasCompositeKey;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stats_today';

    /**
     * The primary key associated with the table.
     *
     * @var array
     */
    protected $primaryKey = ['timestamp', 'symbol'];

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
        'timestamp',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'timestamp'  => 'datetime',
        'unit_price' => 'decimal:4',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $fillable = [
        'timestamp',
        'symbol',
        'unit_price',
        'currency_iso_code',
    ];

    public $incrementing = false;
}

