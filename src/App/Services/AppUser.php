<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;

/**
 * The app's user model, so the package never names it (laravel-admin's is App\Models\User): myfinance2.user_model,
 * else the model of the app's 'users' auth provider. MyFinance2 records belong to these users (user_id).
 */
final class AppUser
{
    /**
     * @return class-string<Model>
     */
    public static function modelClass(): string
    {
        return config('myfinance2.user_model') ?: config('auth.providers.users.model');
    }

    public static function find(int $id): ?Model
    {
        return static::modelClass()::find($id);
    }

    /**
     * The users table.
     */
    public static function table(): string
    {
        return static::_model()->getTable();
    }

    /**
     * Keys a migration's user_id column. With myfinance2.users_foreign_key (default on): a foreign key to the users
     * table in the app's database, so both databases must be on the same MySQL server. Off: a plain index under the
     * same name, for apps whose users live on another server or engine.
     */
    public static function addUserIdKey(Blueprint $table): void
    {
        $keyName = $table->getTable() . '_user_id';

        if (!static::_usesForeignKey()) {
            $table->index('user_id', $keyName);
            return;
        }

        $model    = static::_model();
        $database = $model->getConnection()->getDatabaseName();
        $table->foreign('user_id', $keyName)
            ->references($model->getKeyName())
            ->on($database . '.' . $model->getTable());
    }

    /**
     * Undoes addUserIdKey() before the column is dropped (dropping the column drops a plain index with it).
     */
    public static function dropUserIdKey(Blueprint $table): void
    {
        if (static::_usesForeignKey()) {
            $table->dropForeign($table->getTable() . '_user_id');
        }
    }

    /**
     * Every user id, soft-deleted users included (the crons and backfills iterate over them).
     *
     * @return Collection<int, int|string>
     */
    public static function allIds(): Collection
    {
        $model = static::_model();

        return $model->newQueryWithoutScopes()->pluck($model->getKeyName());
    }

    private static function _usesForeignKey(): bool
    {
        return (bool) config('myfinance2.users_foreign_key', true);
    }

    private static function _model(): Model
    {
        $class = static::modelClass();

        return new $class();
    }
}
