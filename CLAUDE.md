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

## Code Standards
**General:**
- Max line length: 120 characters (PHP, HTML, JavaScript, Laravel views - whenever possible)
- All files must end with a newline (empty last line) for cleaner git diffs
- Minimize business logic in the FE (move business logic in the BE whenever possible)
- Avoid code smells like long methods, large classes, long parameter list, duplicated code, dead code, excessive coupling between classes, etc.)
- Keep file sizes manageable (ideally under 500 lines of code)
- Avoid hardcoding or introducing any user or account data in this repository (including comments). This repository is publicly available
- **Opening braces for all functions/methods (not only constructors) for all languages on next line (Allman style)**, not same line

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

