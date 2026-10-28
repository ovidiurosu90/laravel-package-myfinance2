# MyFinance2

Laravel package for financial portfolio tracking and analysis.

## About This Package
This is a Laravel package used by the main `laravel-admin` project. The relationship is:
- **laravel-admin** (main project) - separate repository, separate database
- **myfinance2** (this package) - separate repository, separate database
- myfinance2 is included in laravel-admin via Composer in vendor/ovidiuro/myfinance2
- Both can be updated independently as needed

### Repository boundaries (keep coupling minimal)
Three repositories work together: **laravel-admin** (the host app), **myfinance2** (this package, public) and
**laravel-package-admin-mydata** (private: backup table list, schema dumps, private config, test data). Keep the
coupling between them to a minimum:
- Couple only as a last resort, when there is no other way. Prefer the package's contracts: its config
  (`config/myfinance2.php`, which the host can override), its gates (`myfinance2.access`, `myfinance2.manage`), events
  and view extension points.
- myfinance2 must work in other Laravel apps: never reference laravel-admin code (`App\...`), its layout, roles,
  permissions or middleware directly; the host supplies them through the config and the gates.
- laravel-admin must keep working without myfinance2 and admin-mydata: guard every reference to them (`class_exists`,
  `@includeIf`, tests that skip when the package is missing).
- Document every coupling point that remains, with a comment at the spot and in the README of the repository that
  depends on the other.

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
php artisan cache:clear && php artisan config:clear
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
The package's own JS / CSS (charts, date pickers, toggles; plus jQuery, Bootstrap, DataTables, selectize and Font
Awesome for apps without them) are built here into `dist/` (`yarn install && yarn build`, commit `dist/`) and
published to the app's `public/vendor/myfinance2` (README, "Assets"). jQuery, Bootstrap, DataTables and selectize come
from the app's bundle in laravel-admin (`yarn install && yarn run prod` there).

## Testing
Each repository tests its own logic: everything MyFinance2 implements is tested here; laravel-admin only tests its own
wiring (its roles -> the gates, its middleware, its layout, its config) plus two exceptions that need its real data
(the Returns regression and holdings tests).
- **Unit tests** (`tests/Unit/`) are pure: no DB, no Laravel boot. Test private methods via reflection. Prefer these
  for anything testable without the database.
- **Feature tests** (`tests/Feature/`) run a Laravel app with only this package (orchestra/testbench) on the host
  app's databases: the local copy of production, there is no test database. From this package's directory:
  `vendor/bin/phpunit --testsuite Unit`, `vendor/bin/phpunit --testsuite Feature` (`composer update` here installs
  the dev dependencies into this package's own `vendor/`; never run it in laravel-admin).

### Writing MyFinance2 feature tests
Extend `ovidiuro\myfinance2\Tests\TestCase`. It reads the host's `.env` (`tests/Support/HostEnvironment`), rolls back
both connections after every test, opens the gates (`$opensTheGates = false` for the package's own deny-by-default)
and resets auth. The leftover-rows check (`tests/Support/LeftoverRowsExtension`, in `phpunit.xml`) fails any run that
leaves rows behind (`stats_today` is ignored: the finance cron rewrites it every minute). Follow these rules so a test
never pollutes real data and never stalls:

1. **Act as a user, never write one.** `existingUser()` is the host's first user (read-only); `userWithoutData()`
   (and `userWithoutData(1)` for a second one) is a user with no MyFinance2 data, never saved. Both are `TestUser`,
   whose `save()` / `delete()` throw. Never `Auth::logout()`: it writes the user's `remember_token`.
2. **Only write synthetic rows.** Use identifiers that cannot exist in production (e.g. symbol `TST.*`). Never insert
   or update a real row, not even rolled back; the `VUSA.AS` benchmark is read-only.
3. **`stats_historical` has no `user_id`,** so the base model's `creating` hook fails on it. Insert via the query
   builder (`DB::connection($conn)->table(...)`), not Eloquent.
4. **Isolate per-user pipelines** (e.g. `CategorizationService::build($userId)`) so they do not walk a real portfolio:
   run them for an unused id (`userWithoutData()`, or `max('user_id') + 1` of the table). The `user_id` columns may
   have a cross-database FK to the host's `users`, so wrap inserts for an unused id in
   `Schema::connection($conn)->withoutForeignKeyConstraints(fn () => ...)` (session-scoped, rolled back).
5. **Avoid live network calls.** The test cache is `array` (cold every run), so any code falling back to
   `HistoricalPriceCache` makes a live Yahoo Finance request per uncovered symbol. Seed prices for the synthetic symbol
   across the date range. Assert deterministic values only on the seeded symbol; for benchmark-relative figures
   (alpha), assert how the value is assembled rather than an exact number, and skip if the benchmark history is
   missing. (`GetFinanceDataEndpointTest` calls Yahoo on purpose.)
6. **Nothing of the host.** No `App\...` class, view, route or config: a test that needs the host's login page or
   layout defines its own (`RouteGateTest::defineRoutes()`, `tests/Fixtures/views/host-layout.blade.php`).

Reference example: `tests/Feature/CategorizationPipelineTest.php` (full pipeline, ~2s, touches no real data).

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
- Has access to jQuery, DataTables, Bootstrap, selectize (the app's or the base bundle), LightweightCharts,
  TempusDominus and bootstrapToggle (dist/myfinance2.js)
- Functions/methods should not exceed 100 lines of code (refactor into smaller units if needed)

**Laravel Views:**
- Mix of HTML, PHP (Blade), and JavaScript
- New code should adhere to these standards as much as possible

**Frontend:**
- HTML/CSS: Follow Bootstrap 5.3 conventions

