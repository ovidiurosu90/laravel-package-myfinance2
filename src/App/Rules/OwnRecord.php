<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Rules;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * An `exists` rule limited to the signed-in user's own rows, so a form can never point a record at another user's
 * account, currency, trade or ledger entry (nor probe which ids exist). The plain `exists:` rule checks the id only.
 */
final class OwnRecord
{
    /**
     * @param class-string<\ovidiuro\myfinance2\App\Models\MyFinance2Model> $modelClass
     */
    public static function of(string $modelClass): Exists
    {
        $model = new $modelClass();

        return Rule::exists($model->getConnectionName() . '.' . $model->getTable(), 'id')
            ->where('user_id', auth()->id());
    }
}
