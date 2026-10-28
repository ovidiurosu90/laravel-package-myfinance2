<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;
use LogicException;

/**
 * The host app's users, read-only: the feature tests act as an existing user (the users table of the app's own
 * database; the tests run on the local copy of production) and never write one.
 */
final class TestUser extends Authenticatable
{
    protected $table = 'users';

    public function save(array $options = []): bool
    {
        throw new LogicException('The feature tests never write a user: act as an existing one (TestCase).');
    }

    public function delete(): ?bool
    {
        throw new LogicException('The feature tests never delete a user.');
    }
}
