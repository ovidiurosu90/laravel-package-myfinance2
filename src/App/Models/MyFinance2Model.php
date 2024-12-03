<?php

namespace ovidiuro\myfinance2\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

class MyFinance2Model extends Model
{
    use SoftDeletes;

    public function getConnectionName()
    {
        return config('myfinance2.db_connection');
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($item)
        {
            $item->user_id = auth()->user()->id;
        });

        static::addGlobalScope(new AssignedToUserScope);
    }
}

