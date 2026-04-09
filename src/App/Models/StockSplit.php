<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Models;

/**
 * Audit log of stock split events recorded by a user.
 * Extends MyFinance2Model for automatic user_id injection and AssignedToUserScope.
 * Soft deletes included (from MyFinance2Model) but no delete routes are exposed.
 */
class StockSplit extends MyFinance2Model
{
    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'id'               => 'integer',
        'user_id'          => 'integer',
        'ratio_numerator'  => 'integer',
        'ratio_denominator' => 'integer',
        'trades_updated'   => 'integer',
        'alerts_adjusted'  => 'integer',
        'split_date'       => 'date',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
        'deleted_at'       => 'datetime',
        'reverted_at'      => 'datetime',
    ];

    protected $fillable = [
        'symbol',
        'split_date',
        'ratio_numerator',
        'ratio_denominator',
        'notes',
        'trades_updated',
        'alerts_adjusted',
    ];

    public function getRatioLabel(): string
    {
        return $this->ratio_numerator . ':' . $this->ratio_denominator;
    }

    public function isReverted(): bool
    {
        return $this->reverted_at !== null;
    }
}
