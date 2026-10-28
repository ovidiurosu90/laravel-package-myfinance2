<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Feature;

use ovidiuro\myfinance2\Tests\TestCase;
use ovidiuro\myfinance2\Tests\Support\TestUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\Finder;
use ovidiuro\myfinance2\App\Services\AppUser;

/**
 * MyFinance2 reaches the app's users only through AppUser (myfinance2.user_model, else the 'users' auth provider's
 * model), never by naming an app's classes, and keys its user_id columns through it. Reads the users only.
 */
class AppUserTest extends TestCase
{
    public function test_it_is_the_apps_user_model_unless_configured(): void
    {
        // Unset: the 'users' auth provider's model (TestCase sets it to TestUser)
        config(['myfinance2.user_model' => null]);
        $this->assertSame(TestUser::class, AppUser::modelClass());
        $this->assertSame('users', AppUser::table());

        config(['myfinance2.user_model' => AppUserTestModel::class]);
        $this->assertSame(AppUserTestModel::class, AppUser::modelClass());
        $this->assertSame('app_user_test_models', AppUser::table());
    }

    public function test_it_finds_users_and_lists_every_id_soft_deleted_included(): void
    {
        $first = $this->existingUser()->id;
        $this->assertSame($first, AppUser::find($first)?->id);

        // A user model with soft deletes: every row counts, the soft-deleted ones too
        config(['myfinance2.user_model' => AppUserTestSoftDeletingModel::class]);
        $all = DB::table('users')->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $this->assertSame($all, AppUser::allIds()->map(fn ($id) => (int) $id)->sort()->values()->all());
    }

    public function test_the_user_id_foreign_key_is_the_one_the_migrations_always_made(): void
    {
        $usersTable = config('database.connections.' . config('database.default') . '.database') . '.users';

        // The user_id column of the create_* migrations and of the add_user_id_to_* ones; before
        // AppUser::addUserIdKey() they chained constrained() onto it
        foreach ([false, true] as $creating) {
            $column = fn (Blueprint $table) => $creating
                ? $table->foreignId('user_id')
                : $table->foreignId('user_id')->after('id')->nullable();

            $before = $this->_sql($creating, fn (Blueprint $table) => $column($table)
                ->constrained(table: $usersTable, indexName: $table->getTable() . '_user_id'));
            $now = $this->_sql($creating, function (Blueprint $table) use ($column)
            {
                $column($table);
                AppUser::addUserIdKey($table);
            });

            $this->assertSame($before, $now);
            $this->assertStringContainsString(
                'foreign key (`user_id`) references ' . str_replace('.', '`.`', "`$usersTable`") . ' (`id`)',
                implode(";\n", $now)
            );
        }

        $drop = $this->_sql(false, fn (Blueprint $table) => AppUser::dropUserIdKey($table));
        $this->assertSame(['alter table `trades` drop foreign key `trades_user_id`'], $drop);
    }

    public function test_without_the_foreign_key_the_user_id_gets_a_plain_index(): void
    {
        config(['myfinance2.users_foreign_key' => false]);

        $sql = $this->_sql(false, function (Blueprint $table)
        {
            $table->foreignId('user_id')->after('id')->nullable();
            AppUser::addUserIdKey($table);
        });

        $this->assertStringNotContainsString('foreign key', implode(";\n", $sql));
        $this->assertContains('alter table `trades` add index `trades_user_id`(`user_id`)', $sql);
        $this->assertSame([], $this->_sql(false, fn (Blueprint $table) => AppUser::dropUserIdKey($table)));
    }

    public function test_the_package_never_imports_the_apps_classes(): void
    {
        $offending = [];
        $finder    = (new Finder())->files()->name('*.php')->in(__DIR__ . '/../../src');
        foreach ($finder as $file) {
            if (preg_match('/^use App\\\\|[^\w\\\\]\\\\App\\\\(Models|Http|Services)\\\\/m', $file->getContents())) {
                $offending[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offending, "Package files naming an app's classes:\n" . implode("\n", $offending));
    }

    /**
     * The SQL a migration's blueprint on the trades table would run; compiled only, never executed.
     *
     * @return list<string>
     */
    private function _sql(bool $creating, \Closure $callback): array
    {
        // Through the schema builder, as the migrations do: it sets the connection's schema grammar
        $connection = Schema::connection(config('myfinance2.db_connection'))->getConnection();
        $blueprint  = new Blueprint($connection, 'trades');
        if ($creating) {
            $blueprint->create();
        }
        $callback($blueprint);

        return $blueprint->toSql();
    }
}

/**
 * A stand-in user model, for the configured case.
 */
class AppUserTestModel extends \Illuminate\Database\Eloquent\Model
{
}

/**
 * The users table through a model with soft deletes, read only.
 */
class AppUserTestSoftDeletingModel extends \Illuminate\Database\Eloquent\Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $table = 'users';
}
