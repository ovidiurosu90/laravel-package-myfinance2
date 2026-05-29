# MyFinance2

Laravel package for financial portfolio tracking and analysis.

## About This Package
This is a Laravel package used by the main `laravel-admin` project. The relationship is:
- **laravel-admin** (main project) - separate repository, separate database
- **myfinance2** (this package) - separate repository, separate database
- myfinance2 is included in laravel-admin via Composer in vendor/ovidiuro/myfinance2
- Both can be updated independently as needed

## Constraints & Guidelines
Attention! Avoid running disruptive actions that are hard to revert (git checkout, git pull, git add, git commit, git reset, git push, composer update). Can suggest them as a last resort, but they should be run manually.

**File permissions when creating files:**
- Regular files: use 644, then try 664 if it fails
- Executable files: use 755, then try 775 if it fails

**Fix storage framework permissions** (run in main laravel-admin project):
```bash
sudo chown $USER:www-data -R storage/framework/
sudo chmod g+w -R storage/framework/
```

**Clear cache if needed** (run in main laravel-admin project):
```bash
php artisan cache:clear && php artisan config:cache
```

## Tech Stack
- Laravel 12 / PHP 8.1+
- Blade templates + Bootstrap 5.3
- Yahoo Finance API integration
- JavaScript: Custom scripts in `<script type="module">` for lib access (jQuery, DataTables, Bootstrap, etc)

## Project Structure
**In myfinance2 package:**
- `src/App/Http/Controllers/` - Route handlers
- `src/App/Services/` - Business logic
- `src/resources/views/` - Blade templates
- `src/routes/web.php` - Route definitions
- `src/resources/lang/` - Translations
- `src/config/` - Configuration files

**In main laravel-admin project (overrides):**
- `resources/views/vendor/` - View overrides
- `resources/lang/vendor/` - Translation overrides
- `app/Models/Overrides/`, `app/Http/Controllers/Overrides/`, etc. - PHP module overrides

## Frontend Assets
Frontend assets are built from the main **laravel-admin** project using `yarn install && yarn run prod` (not in this package directly).

## Testing
- **Main project tests**: `php artisan test` (run from laravel-admin)
- **Package tests**: `php vendor/bin/phpunit --testdox` (run from myfinance2)
- **Unit tests** (this package, `tests/Unit/`) are pure: no DB, no Laravel boot. Test private methods via reflection. Prefer these for anything testable without the database.
- **Feature/integration tests** live in the **laravel-admin** project at `tests/Packages/MyFinance2/Feature/` (run with `php artisan test`), because they need the Laravel context and database.

### Writing MyFinance2 feature tests
Feature tests run against the **production database** (no separate test DB; `RefreshDatabase` is intentionally unused). Follow these rules so a test never pollutes real data and never stalls:

1. **Always roll back.** `use DatabaseTransactions;` and transact both connections, since myfinance2 models use a second connection:
   ```php
   public function connectionsToTransact(): array
   {
       return [null, config('myfinance2.db_connection', 'myfinance2_mysql')];
   }
   ```
2. **Never save a User model.** Saving one triggers virtual attributes (e.g. `theme`) and lock-wait timeouts. Use `actingAs(User::first())` read-only (skip if absent) and `Auth::forgetGuards()` in `tearDown()`. Do not use `User::factory()->create()`.
3. **Only write synthetic rows.** Use identifiers that cannot exist in production (e.g. symbol `TST.*`). Never insert or update a real row, not even rolled back; the `VUSA.AS` benchmark is read-only.
4. **`stats_historical` has no `user_id`,** so the base model's `creating` hook fails on it. Insert via the query builder (`DB::connection($conn)->table(...)`), not Eloquent.
5. **Isolate per-user pipelines** (e.g. `CategorizationService::build($userId)`) so they do not walk a real portfolio. Insert the synthetic trade under an unused id, then call `build()` for it:
   ```php
   $userId = (int) DB::connection($conn)->table('trades')->max('user_id') + 1;
   ```
   `trades.user_id` has a cross-database FK to the admin `users` table, so wrap the synthetic trade insert in `Schema::connection($conn)->withoutForeignKeyConstraints(fn () => ...)` (session-scoped, rolled back) to accept the unused id without referencing a real user.
6. **Avoid live network calls.** The test cache driver is `array` (cold every run), so any code falling back to `HistoricalPriceCache` makes a live Yahoo Finance request per uncovered symbol (this is what makes an un-isolated `build()` take ~30s). Seed prices for the synthetic symbol across the date range so `DrawdownService` never hits that path. Assert deterministic values only on the seeded symbol; for benchmark-relative figures (alpha), assert how the value is assembled rather than an exact number, and skip if the benchmark history is missing.

Reference example: `laravel-admin/tests/Packages/MyFinance2/Feature/CategorizationPipelineTest.php` (full pipeline, ~2s, touches no real data).

## Code Standards
**General:**
- Max line length: 120 characters (PHP, HTML, JavaScript, Laravel views - whenever possible)
- All files must end with a newline (empty last line) for cleaner git diffs
- Minimize business logic in the FE (move business logic in the BE whenever possible)
- Avoid code smells like long methods, large classes, long parameter list, duplicated code, dead code, excessive coupling between classes, etc.)
- Keep file sizes manageable (ideally under 500 lines of code)
- Avoid hardcoding or introducing any user or account data in this repository (including comments). This repository is publicly available
- **Opening braces for all functions/methods (not only constructors) for all languages on next line (Allman style)**, not same line
- **Never use `number_format()` directly**; always route numeric formatting through `MoneyFormat::` methods. Exception: URL/query-string parameters that must not have thousands separators (use `number_format($v, $decimals, '.', '')` there only)
- **Punctuation**: avoid em dashes (`—`) and en dashes (`–`) in generated text; use proper English punctuation instead (commas, semicolons, colons, or rewrite the sentence)

**PHP:**
- PHP 8.1+ strict types
- PSR-12 style guide (with exceptions below)
- Type hints required
- Functions/methods should not exceed 100 lines of code (refactor into smaller units if needed)
- Avoid raw SQL queries; use Eloquent models instead (each repository has its own database)
- Prefix the private properties & methods with underscore

**JavaScript:**
- Airbnb JavaScript Style Guide (https://github.com/airbnb/javascript) (with exceptions below)
- Custom scripts in `<script type="module">` tags
- Has access to jQuery, DataTables, Bootstrap, etc from the build
- Functions/methods should not exceed 100 lines of code (refactor into smaller units if needed)

**Laravel Views:**
- Mix of HTML, PHP (Blade), and JavaScript
- New code should adhere to these standards as much as possible

**Frontend:**
- HTML/CSS: Follow Bootstrap 5.3 conventions

