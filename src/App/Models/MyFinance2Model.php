<?php

namespace ovidiuro\myfinance2\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MyFinance2Model extends Model
{
    use SoftDeletes;

    public function getConnectionName()
    {
        return config('myfinance2.db_connection');
    }
}

