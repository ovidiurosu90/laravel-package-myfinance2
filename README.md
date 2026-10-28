# Laravel Package MyFinance2

Laravel package for managing personal finances

- accounts, currencies, ledger, funding
- trades, orders, positions, cash balances, dividends, stock splits
- watchlist, with tiers (settled tier, tier signals) and the tier readings and warnings on orders and trades
- returns per year, timeline, overview charts
- alerts: price alerts, peak proximity, dip buying plan, portfolio peak


## Images

![Finance Dashboard](./images/finance_dashboard.png "Finance Dashboard")

![Funding](./images/funding.png "Funding")

![Watchlist](./images/watchlist.png "Watchlist")

![Open Positions](./images/open_positions.png "Open Positions")

![Create Trade](./images/trade_create.png "Create Trade")

More images in the [images folder](images).


## Installation instructions

In any Laravel 12 app with users and a login page:

1. `composer require ovidiuro/myfinance2` (the service provider is auto-discovered).
2. Create the MyFinance2 database and its user (below); set `MYFINANCE2_DB_*` in the app's `.env`. Its tables live
   there, its records belong to the app's users (`user_id`).
3. `php artisan migrate` (preview it with `--pretend`; undo with `php artisan migrate:rollback`).
4. Define the two gates, `myfinance2.access` and `myfinance2.manage` ("Authorization"); until then everyone is denied.
5. Optionally, in the app's `config/myfinance2.php` (only the keys it sets override the package's): its own
   middleware ("Authorization"), its layout and section names ("Layout"), its navbar registry ("Navbar"), a URL
   prefix and any area setting ("Configuration").
6. `php artisan vendor:publish --tag=myfinance2-assets` once; Laravel's `laravel-assets` tag republishes them on every
   `composer update` ("Assets").
7. Fire Laravel's `Illuminate\Auth\Events\Verified` when an account is verified, for the starter currencies
   ("Onboarding"); set up the server ("Server setup") and schedule the crons ("Commands and crons") for live prices,
   charts and alerts.

The pages are at `/finance-home`, `/positions`, ... (`route('myfinance2::home')`); the app links to them by route name.

```bash
mysql -uroot -p
    CREATE DATABASE [MYFINANCE2_DB_DATABASE];
    CREATE USER '[MYFINANCE2_DB_USERNAME]'@'localhost' IDENTIFIED WITH mysql_native_password BY '[MYFINANCE2_DB_PASSWORD]';
    GRANT ALL PRIVILEGES ON [MYFINANCE2_DB_DATABASE].* TO '[MYFINANCE2_DB_USERNAME]'@'localhost';

    -- For the user_id foreign keys (skip with MYFINANCE2_USERS_FOREIGN_KEY=false, see "Authorization")
    GRANT SELECT ON [DB_DATABASE].users TO '[MYFINANCE2_DB_USERNAME]'@'localhost';
    GRANT REFERENCES ON [DB_DATABASE].users TO '[MYFINANCE2_DB_USERNAME]'@'localhost';
    FLUSH PRIVILEGES;

    exit

mysql -u[MYFINANCE2_DB_USERNAME] -p [MYFINANCE2_DB_DATABASE] # use [MYFINANCE2_DB_PASSWORD] set above
```

### Authorization

MyFinance2 asks the app who may use it through two gates, and **denies everyone until the app defines them**, so
installing the package never exposes anyone's finance data. Until then every MyFinance2 page answers 403 with
"MyFinance2 access is not configured: the app has to define the 'myfinance2.access' gate" and the navbar leaves the
Finance menu out.

- `myfinance2.access`: every MyFinance2 page and AJAX endpoint, and the Finance navbar menu
- `myfinance2.manage`: the create / edit forms of one area, passed as the argument: `accounts`, `alerts`,
  `cashbalances`, `currencies`, `dividends`, `ledger`, `orders`, `splits`, `trades`, `watchlistsymbols`
  (`MyFinance2ServiceProvider::MANAGED_AREAS`)

Define them in the app, e.g. in `app/Providers/AuthServiceProvider.php`:

```php
Gate::define('myfinance2.access', fn (User $user) => $user->is_admin);
Gate::define('myfinance2.manage', fn (User $user, string $area) => $user->is_admin);
```

laravel-admin maps them to its laravel-roles roles and permissions (see its `AuthServiceProvider`).

Every MyFinance2 route runs `web`, then the app's own middleware from `myfinance2.middleware` (empty by default), then
the access gate, which cannot be configured away. Add yours in the app's `config/myfinance2.php` (only the keys it
sets override the package's defaults), e.g. laravel-admin's activity log and blocker:

```php
return [
    'middleware' => ['activity', 'checkblocked'],
];
```

MyFinance2 records belong to the app's users (`user_id`). The package finds the user model through
`App\Services\AppUser`: `myfinance2.user_model` (env `MYFINANCE2_USER_MODEL`), else the model of the app's `users`
auth provider, so most apps set nothing.

The migrations give each `user_id` column a cross-database foreign key to the users table in the app's database
(`<app database>.users`), which needs both databases on the same MySQL server and the `REFERENCES` grant above. When
the users live on another server or engine, set `myfinance2.users_foreign_key` (env `MYFINANCE2_USERS_FOREIGN_KEY`) to
`false` **before the first migrate**: the columns then get a plain index under the same name
(`AppUser::addUserIdKey()`). Switching it after migrating changes nothing in the schema and breaks the rollback of
those migrations. Either way every `user_id` is `NOT NULL` and set on create from the signed-in user
(`MyFinance2Model`); without the foreign key, removing a deleted user's MyFinance2 records is up to the app.

### Onboarding

MyFinance2 does not create accounts or assign roles; the app decides who gets in (the gates above). When the app
fires Laravel's `Illuminate\Auth\Events\Verified` for a user (e.g. on email verification or account activation), the
package gives that user the starter currencies (UNK, USD, EUR), if they pass `myfinance2.access` and have none yet.
laravel-admin fires it on activation and gives the finance role to registrations carrying its finance invite token
(`MYFINANCE2_INVITE_TOKEN`, in its own `config/auth.php`).

### Navbar

`myfinance2::partials.nav` is the Finance navbar menu (shown to users that pass `myfinance2.access`). Either include
it in the app's navbar yourself, or point `myfinance2.navbar_registry` at the config key that lists the app's navbar
partials and the package adds itself there at boot, e.g. laravel-admin:

```php
// config/myfinance2.php
'navbar_registry' => 'navigation.menus',
```

### Layout

Every MyFinance2 page extends `myfinance2::layouts.app` and fills its own sections (`myfinance2_title`,
`myfinance2_styles`, `myfinance2_content`, `myfinance2_scripts`). Without more config they render in the package's
minimal page (`myfinance2::layouts.standalone`: a navbar with the Finance menu). To show them in the app's own layout,
name it and the sections it yields for the page title, `<head>` styles, content and end-of-`<body>` scripts, e.g.
laravel-admin:

```php
// config/myfinance2.php
'layout'          => 'layouts.app',
'layout_sections' => [
    'title'   => 'template_title',
    'styles'  => 'template_linked_css',
    'content' => 'content',
    'scripts' => 'footer_scripts',
],
'flash_messages'  => false, // its layout already shows session('success') / session('error') and $errors
```

With `flash_messages` on (the default), the package shows those messages above each page itself.

### Assets

The package ships its JS / CSS prebuilt in `dist/`, so the app needs no Node build for them; the layout loads them
from `public/vendor/myfinance2`. They are published with the `myfinance2-assets` tag and with `laravel-assets`, which
Laravel apps republish on every `composer update` (`post-update-cmd`); by hand:

```bash
php artisan vendor:publish --tag=myfinance2-assets --force
```

- `myfinance2.js` / `.css`: the libraries only MyFinance2 pages use (lightweight-charts, Tempus Dominus,
  bootstrap5-toggle), on the page's jQuery.
- `myfinance2-base.js` / `.css`: jQuery, Bootstrap, DataTables, selectize and Font Awesome, loaded before it by the
  standalone layout, or with an app layout that lacks them (`myfinance2.base_assets` => `true`). laravel-admin's
  layout loads its own (Vite bundle), so it leaves this off.

After changing the entries (`src/resources/assets/js/`) or the libraries (`package.json`), rebuild and commit `dist/`:

```bash
cd vendor/ovidiuro/myfinance2 && yarn install && yarn build
```

### Configuration

All of MyFinance2's config lives under `myfinance2.*`: `src/config/myfinance2.php`, plus one file per area read as
`myfinance2.<area>` (`general`, `ledger`, `trades`, `watchlistsymbols`, `alerts`;
`MyFinance2ServiceProvider::CONFIG_AREAS`). Override a value in the app's `config/myfinance2.php`; an area's keys
merge one level deep, so the app sets only the keys it changes:

```php
return [
    'trades' => [
        'delisted_symbols' => ['XYZ'],
    ],
];
```

The env names all start with `MYFINANCE2_`.

The pages and AJAX endpoints sit at the root (`/positions`, `/get-finance-data`, ...); set `myfinance2.route_prefix`
(env `MYFINANCE2_ROUTE_PREFIX`), e.g. `finance`, to move them under `/finance/...`, out of the way of the app's own
routes. Every route is named `myfinance2::...` and the package builds its links from the names, so link to MyFinance2
pages the same way: `route('myfinance2::positions')`.


## Server setup

The commands below and in "Commands and crons" run in the app's directory, `[APP_PATH]` (e.g.
`~/Repositories/laravel-admin`), and the crons as the web server's user.

### Market status (used by /positions)

`src/scripts/market_status.py` (shipped executable) runs on `/usr/bin/python3`, so its library goes into the system
Python. On Ubuntu 23.04+ pip refuses system-wide installs (PEP 668); add `--break-system-packages` there.

```bash
sudo apt-get update
sudo apt install python3-pip
sudo pip install pandas-market-calendars

# Test
./vendor/ovidiuro/myfinance2/src/scripts/market_status.py 'LSE'
```

### curl-impersonate (avoids '429 Too Many Requests' from Yahoo Finance)

```bash
mkdir ~/curl-impersonate/
cd ~/curl-impersonate/
wget https://github.com/lwthiker/curl-impersonate/releases/download/v0.6.1/libcurl-impersonate-v0.6.1.x86_64-linux-gnu.tar.gz
tar -xf libcurl-impersonate-v0.6.1.x86_64-linux-gnu.tar.gz

sudo ln -s ~/curl-impersonate/libcurl-impersonate-chrome.so /usr/local/lib/
ls -la /usr/local/lib/libcurl-impersonate-chrome.so
```

### Storage for the account overview and symbol charts

```bash
cd [APP_PATH]/storage/
sudo chown -R :www-data app
sudo chmod -R 775 app
```

### Log files and cpulimit

```bash
cd [APP_PATH]/
>storage/logs/finance-api-cron.log
>storage/logs/stats-cron.log
chown :www-data storage/logs/finance-api-cron.log storage/logs/stats-cron.log
ls -la storage/logs/finance-api-cron.log storage/logs/stats-cron.log
tail -f storage/logs/finance-api-cron.log storage/logs/stats-cron.log

sudo apt install cpulimit
```


## Commands and crons

### Cron schedule

Each hourly job has its own minute and each `@reboot` job its own delay, so no two run at once. A new cron takes a
free minute and a free delay; update this table, the one place that lists them all.

| When | Job | After reboot |
|------|-----|--------------|
| every minute (at :05 s; optionally also :35 s) | `app:finance-api-cron`: quotes, exchange rates, account overview, price alerts | |
| daily at 06:01 | `app:finance-api-cron --historical-account-overview` (the past week) | 150 s |
| hourly at :12 | `finance:peak-proximity-alerts` | 480 s |
| hourly at :24 | `app:finance-api-cron --refresh-returns` | 250 s |
| hourly at :33 | `finance:dip-buying-alerts` | 510 s |
| hourly at :42 | `app:stats-cron` | 350 s |
| hourly at :48 | `finance:portfolio-peak-alerts` | 540 s |
| hourly at :54 | `app:finance-api-cron --refresh-symbol-performance` | 400 s |

All run under `cpulimit -l 50` and log to `storage/logs/finance-api-cron.log` (`app:stats-cron` to `stats-cron.log`),
so their WARNING+ entries surface in the host's log digest (laravel-admin's `logs:email-daily-errors`). The jobs that
read live quotes from Yahoo Finance load curl-impersonate (`LD_PRELOAD`, `CURL_IMPERSONATE`); dip buying and portfolio
peak read cached data only. To limit an hourly alert cron to market hours (08:00 to 22:00 server time), use e.g.
`12 8-22 * * *`.

### app:finance-api-cron

```bash
sudo su
crontab -e

#############
# Purpose: Keeps your portfolio data fresh with live market prices
# - refreshQuotes(): Fetches current market prices for all symbols (stocks) in your trades, dividends, and watchlist
# - refreshExchangeRates(): Fetches current exchange rates for all currency pairs used in multi-currency trades
# - refreshAccountOverview(): Calculates and persists current account statistics (total cost, market value, change, cash balance) and builds real-time charts

* * * * * ( sleep 5; su - www-data -s /bin/bash -c "export LOG_CHANNEL=stdout; export LD_PRELOAD=/usr/local/lib/libcurl-impersonate-chrome.so; export CURL_IMPERSONATE=chrome116; cd [APP_PATH]/ && cpulimit -l 50 -- php artisan app:finance-api-cron >> [APP_PATH]/storage/logs/finance-api-cron.log 2>&1" )

# Uncomment the next line if you want to have twice-per-minute updates
#* * * * * ( sleep 35; su - www-data -s /bin/bash -c "export LOG_CHANNEL=stdout; export LD_PRELOAD=/usr/local/lib/libcurl-impersonate-chrome.so; export CURL_IMPERSONATE=chrome116; cd [APP_PATH]/ && cpulimit -l 50 -- php artisan app:finance-api-cron >> [APP_PATH]/storage/logs/finance-api-cron.log 2>&1" )
#############

#############
# Purpose: Maintains a complete week of historical account performance data for trend analysis and recovery after downtime
# For each historical date:
# - Recalculates account statistics as they were on that specific date
# - Persists: cost, market value, change, cash balance per account
# - Rebuilds historical chart data points

# Run the job every day at 06:01 => get the past week
# Cron does not run $(...) in variables: it passes the text on, and the inner 'bash -c' runs it at each run. The dates
# stay in variables because a '%' on a command line would need escaping ('\%').
HISTORICAL_START=$(date +%Y-%m-%d --date '-8 day')
HISTORICAL_END=$(date +%Y-%m-%d --date '-1 day')

01 06 * * * su - www-data -s /bin/bash -c "export LOG_CHANNEL=stdout; export LD_PRELOAD=/usr/local/lib/libcurl-impersonate-chrome.so; export CURL_IMPERSONATE=chrome116; cd [APP_PATH]/ && cpulimit -l 50 -- php artisan app:finance-api-cron --historical-account-overview --start=${HISTORICAL_START} --end=${HISTORICAL_END} >> [APP_PATH]/storage/logs/finance-api-cron.log 2>&1"

@reboot sleep 150 && su - www-data -s /bin/bash -c "export LOG_CHANNEL=stdout; export LD_PRELOAD=/usr/local/lib/libcurl-impersonate-chrome.so; export CURL_IMPERSONATE=chrome116; cd [APP_PATH]/ && cpulimit -l 50 -- php artisan app:finance-api-cron --historical-account-overview --start=${HISTORICAL_START} --end=${HISTORICAL_END} >> [APP_PATH]/storage/logs/finance-api-cron.log 2>&1"
#############

#############
# Purpose: Pre-caches returns data for all years to ensure fast page loads
# - Past years: Only refreshes if cache expired (4 weeks TTL), otherwise skips
# - Current year: Always refreshes (1 hour TTL)
# Benefits:
# - Users get instant page loads when viewing /returns
# - Minimizes Yahoo Finance API calls by leveraging long-term cache for historical data
# - Ensures current year data stays fresh with hourly updates

24 * * * * su - www-data -s /bin/bash -c "export LOG_CHANNEL=stdout; export LD_PRELOAD=/usr/local/lib/libcurl-impersonate-chrome.so; export CURL_IMPERSONATE=chrome116; cd [APP_PATH]/ && cpulimit -l 50 -- php artisan app:finance-api-cron --refresh-returns >> [APP_PATH]/storage/logs/finance-api-cron.log 2>&1"

@reboot sleep 250 && su - www-data -s /bin/bash -c "export LOG_CHANNEL=stdout; export LD_PRELOAD=/usr/local/lib/libcurl-impersonate-chrome.so; export CURL_IMPERSONATE=chrome116; cd [APP_PATH]/ && cpulimit -l 50 -- php artisan app:finance-api-cron --refresh-returns >> [APP_PATH]/storage/logs/finance-api-cron.log 2>&1"
#############

#############
# Purpose: Pre-caches watchlist symbol performance tags (gains, windows, metrics) for all users
# - Clears and rebuilds the 2-hour performance cache for every user
# - Ensures /watchlist-symbols page loads instantly without on-the-fly gain computation
# - Cache TTL: 2 hours; cron re-warms it hourly

54 * * * * su - www-data -s /bin/bash -c "export LOG_CHANNEL=stdout; export LD_PRELOAD=/usr/local/lib/libcurl-impersonate-chrome.so; export CURL_IMPERSONATE=chrome116; cd [APP_PATH]/ && cpulimit -l 50 -- php artisan app:finance-api-cron --refresh-symbol-performance >> [APP_PATH]/storage/logs/finance-api-cron.log 2>&1"

@reboot sleep 400 && su - www-data -s /bin/bash -c "export LOG_CHANNEL=stdout; export LD_PRELOAD=/usr/local/lib/libcurl-impersonate-chrome.so; export CURL_IMPERSONATE=chrome116; cd [APP_PATH]/ && cpulimit -l 50 -- php artisan app:finance-api-cron --refresh-symbol-performance >> [APP_PATH]/storage/logs/finance-api-cron.log 2>&1"
#############
```

### Historical data (app:finance-api-cron --historical)

```bash
# Clear the caches
sudo chown $USER:www-data -R storage/framework/
sudo chmod g+w -R storage/framework/
php artisan cache:clear && php artisan config:clear

# Full rebuild only: delete the stored chart files; the runs below and the minutely cron write them again
# sudo rm -rf storage/app/charts/*

# Daily prices per symbol (and exchange rates) from Yahoo Finance (NOT in crontab); dates as Y-m-d
sudo su - www-data -s /bin/bash -c "export LOG_CHANNEL=stdout; cd [APP_PATH]/ && php artisan app:finance-api-cron --historical --start=[START_DATE] --end=[END_DATE]"

# Daily account overview (cost, market value, change, cash) per account; needs the prices above, so start
# the --historical range a few days earlier
sudo su - www-data -s /bin/bash -c "export LOG_CHANNEL=stdout; cd [APP_PATH]/ && php artisan app:finance-api-cron --historical-account-overview --start=$(date +%Y-%m-%d --date '-8 day') --end=$(date +%Y-%m-%d --date '-1 day')"

# Missing exchange rates (e.g. warnings around Jan 1 on /returns): the same command fetches them for the range
# sudo su - www-data -s /bin/bash -c "export LOG_CHANNEL=stdout; cd [APP_PATH]/ && php artisan app:finance-api-cron --historical --start=2024-12-30 --end=2025-01-02"

# One symbol (e.g. after a stock split): overwrites the range with today's split-adjusted prices, redraws its chart
sudo su - www-data -s /bin/bash -c "export LOG_CHANNEL=stdout; cd [APP_PATH]/ && php artisan app:finance-api-cron --historical --symbol=SYMBOL --start=2025-01-01"
```

The single-symbol backfill is also in the UI: **Populate historical data** in a symbol's expand-chart modal (on
positions or the watchlist) refills 2025-01-01 to today and redraws the chart.

### Returns (app:finance-api-cron --refresh-returns)

```bash
# Pre-cache /returns: past years only when their 4-week cache expired, the current year always (1 hour TTL)
# (In crontab hourly at :24; also run ad hoc when needed)
sudo su - www-data -s /bin/bash -c "export LOG_CHANNEL=stdout; cd [APP_PATH]/ && php artisan app:finance-api-cron --refresh-returns"

# --force rebuilds every year (after config changes, price overrides, etc.)
sudo su - www-data -s /bin/bash -c "export LOG_CHANNEL=stdout; cd [APP_PATH]/ && php artisan app:finance-api-cron --refresh-returns --force"
```

**Missing-price email.** When a recomputed year values a held position at 0 for lack of a price (no `price_overrides`
entry and no close within 7 days before Jan 1 / Dec 31; typically a delisted symbol), the command logs a WARNING and
emails the account owner (`MYFINANCE2_ALERTS_EMAIL_TO`, else the owner's email), again only when the gaps change or
once a day while they persist. The returns page and `test_all_held_positions_have_a_price` flag it too. Fix it by
adding the missing closes to `price_overrides` (and the symbol to `delisted_symbols`), then run the `--force` refresh.

### Daily tier history (finance:tier-history-backfill)

```bash
# Rebuild symbol_tier_history: each weekday's tier per traded or watchlist symbol (idempotent upsert).
# Run once after the migration, and again after editing past trades; the hourly --refresh-symbol-performance
# cron rewrites the last 7 weekdays. Derived data: not in the git backup, synced from prod by
# scripts/sync-prod-db.sh (PROD_SYNC_TABLES). (NOT in crontab)

# Dry run: prints the weekdays per tier per symbol, writes nothing (add --symbol=SYMBOL for one symbol)
sudo su - www-data -s /bin/bash -c "cd [APP_PATH]/ && php artisan finance:tier-history-backfill --user-id=1 --dry-run"
# Real run (default: 2025-01-01 to today; --from / --to to narrow it)
sudo su - www-data -s /bin/bash -c "cd [APP_PATH]/ && php artisan finance:tier-history-backfill --all-users"
# Compare today's computed basis with the live page (lists differences above 0.5 points)
sudo su - www-data -s /bin/bash -c "cd [APP_PATH]/ && php artisan finance:tier-history-backfill --user-id=1 --verify-today"
```

- **Tier basis.** Every symbol is tiered on its trailing 1Y market return (`MYFINANCE2_TIER_BASIS=market`, the
  default); `own` restores the legacy rule (owned positions tiered on your own return). After changing it, clear the
  cache and re-run the backfill.
- **Settled tier.** The tier shown everywhere is the 12-month median of the daily readings. It follows today's reading
  with under 60 weekdays of history or after a lasting drop; a manual override still wins. Today's reading is not
  shown on its own; the tier signals below compare it with the last 12 months.
- **Readings on top of the tier**, one chip per symbol on the watchlist Tier line and column and in the
  peak-proximity page, inbox and emails:
  - *Range-bound* (`RangeBoundPattern`, `myfinance2.watchlistsymbols.range_bound`): a stock you trade well that swings
    widely but ends the year flat. TRIM near the 52-week high, ADD near the low (BUY when not held).
  - *Weak tier in profit* (`WeakTierProfit`, `myfinance2.watchlistsymbols.weak_tier_profit`): a held Rust / Bronze
    position in profit. TAKE PROFIT.
  - *Tier signals* (`TierSignalChip`, `myfinance2.watchlistsymbols.tier_history`): today's tier against the last 12
    months. Rare high SELL / TRIM, rare low ADD / BUY, deteriorating REVIEW; severity in `TierSignalSeverity`.
  - *Near peak* (`NearPeakReading`), next to them: near a 3M / 6M / 1Y / 2Y peak; dimmed when the alert does not email.
- **Signal backtest.** `/peak-proximity-alerts/backtest`, or `php artisan finance:tier-signal-backtest --user-id=N
  [--from=Y-m-d] [--rare-share=20] [--consistency=50] [--grid]`, replays these readings on the stored history without
  look-ahead and compares each episode with VUSA.AS 21 / 63 / 126 trading days later. A sanity check, not proof; the
  first results are noted in `TierSignalChip`.
- **Missing prices.** Early 2025 days need 2024 prices; those missing from `stats_historical` are fetched through the
  7-day `HistoricalPriceCache` (never stored), unless `--no-fetch`. A held symbol with no stored close on a day is
  skipped and reported: populate its history and re-run.

### Tier readings and warnings on orders and trades (finance:tier-snapshot-backfill)

Orders and trades save the watchlist tier reading at decision time (`tier_snapshots`, locked; in the git backup, since
a live reading cannot be rebuilt). Order create / edit warns against the tiers (`OrderTierWarnings`, levels in
`myfinance2.watchlistsymbols.order_warnings.levels`: serious asks for a confirmation on save, soft is a banner).

```bash
# Calibrate the warning levels on past trades vs VUSA.AS (read-only)
sudo su - www-data -s /bin/bash -c "cd [APP_PATH]/ && php artisan finance:order-warning-review --user-id=1"
# Readings for orders and trades without one, rebuilt from symbol_tier_history (once after the migration;
# --dry-run first). (NOT in crontab)
sudo su - www-data -s /bin/bash -c "cd [APP_PATH]/ && php artisan finance:tier-snapshot-backfill --user-id=1"
# Replace one reading: --force needs --id and --only (for a filled order: the order, then its trade)
sudo su - www-data -s /bin/bash -c "cd [APP_PATH]/ && php artisan finance:tier-snapshot-backfill --user-id=1 --only=orders --id=N --force"
```

### app:stats-cron

```bash
sudo su
crontab -e

#############
# Prevents accumulation of stale real-time statistics, maintains clean database
# - cleanupStatsToday(): Deletes old rows from stats_today table (rows in stats_today with data from yesterday or before)

42 * * * * su - www-data -s /bin/bash -c "export LOG_CHANNEL=stdout; cd [APP_PATH]/ && cpulimit -l 50 -- php artisan app:stats-cron >> [APP_PATH]/storage/logs/stats-cron.log 2>&1"

@reboot sleep 350 && su - www-data -s /bin/bash -c "export LOG_CHANNEL=stdout; cd [APP_PATH]/ && cpulimit -l 50 -- php artisan app:stats-cron >> [APP_PATH]/storage/logs/stats-cron.log 2>&1"
#############
```

### Price alerts (finance:suggest-alerts)

The minutely `app:finance-api-cron` evaluates the active price alerts of `/price-alerts` and emails the ones that
reach their target (see "Emails"). `finance:suggest-alerts` (also the **Suggest alerts** button on `/price-alerts`)
creates a PRICE_ABOVE alert per open position, a threshold below its closing high (2Y lookback if held 2+ years, else
1Y), skipping symbols that already have an active or paused one. Not scheduled; run it on demand.

```bash
# Preview for one user without creating alerts
php artisan finance:suggest-alerts --user-id=1 --dry-run

# All users with open positions; a custom threshold, or specific symbols only
php artisan finance:suggest-alerts --all-users
php artisan finance:suggest-alerts --user-id=1 --threshold=5 --symbols=AMD,ETH-EUR
```

Env (all optional): `MYFINANCE2_ALERTS_ENABLED` (true); `MYFINANCE2_ALERTS_EMAIL_TO` (the user's email);
`MYFINANCE2_ALERTS_EVAL_INTERVAL_MINUTES` (5: how often the minutely cron evaluates them);
`MYFINANCE2_ALERTS_EVAL_MAX_SECONDS` (20: the time budget per run, the rest wait for the next);
`MYFINANCE2_ALERTS_MARKET_HOURS_ONLY` (false); `MYFINANCE2_SUGGESTION_THRESHOLD_PCT` (3).

### finance:peak-proximity-alerts

Emails an exit hint when an open position is near its peak: within 2% of the 3M peak, 5% of the 6M, 8% of the 1Y or
10% of the 2Y. The full rule set is on the `/peak-proximity-alerts` page.

- **Gate.** Only a weak settled tier (Rust or Bronze) with a meaningful window (6M, 1Y or 2Y) near peak emails.
  Strong tiers show in the inbox as informational; a 3M-only signal is context.
- **Same command, same opt-in, inbox and history**, whatever the tier:
  - *Range-bound* (`PeakProximityRangeSignals`): TRIM in the top 30% of the 52-week range (`RANGE_HIGH`), ADD in the
    bottom 30% (`RANGE_LOW`), BUY in the bottom 30% for a symbol you exited (`RANGE_BUY`). No near-peak alert. ADD
    and BUY email; TRIM is an inbox card only (no edge in the backtest), unless listed in
    `MYFINANCE2_PEAK_PROXIMITY_RANGE_EMAILS`.
  - *TAKE PROFIT* (`PeakProximityTakeProfitSignals`, `TAKE_PROFIT`): a weak tier in profit, near peak or not, in
    place of the near-peak alert. HIGH with a meaningful window near peak or RSI overbought.
  - *Tier signals* (`PeakProximityTierSignals`, `RARE_HIGH` / `RARE_LOW` / `DETERIORATING`). Inbox cards for your
    awareness, not emailed, unless listed in `MYFINANCE2_PEAK_PROXIMITY_TIER_SIGNAL_EMAILS` (e.g. `RARE_HIGH`).
  - On a day both would email, an emailed tier signal goes first and the near-peak one waits.
- **Cadence.** One email when an alert opens, then reminders until dismissed: near-peak every 7 / 3 / 1 days with
  one / two / three long windows near peak (a new long window emails at once); the others every
  `range_reminder_days` / `take_profit_reminder_days` / `tier_signal_reminder_days` (7). The Re-arm button on
  `/peak-proximity-alerts` clears today's record, so a symbol can alert again the same day.
- **Email.** The three watchlist-symbols cards for the symbol plus price, distance from the peak and the gain if sold
  now. The subject leads with the tier, e.g. `[MyFinance2] EZJ.L (Bronze, EXIT) near peak => -4.25% to 6M`;
  reminders are prefixed `Reminder:`.
- **Inbox** (`/peak-proximity-alerts/inbox`): a card per alert, sorted by severity, kept until dismissed. Dismissing
  ends the reminders; a later return to peak opens a fresh alert.

```bash
sudo su
crontab -e

#############
# Purpose: Hourly exit-hint email when an open position is near its 3M/6M/1Y/2Y peak.
# Throttled to one email per symbol per day, so running it every hour is safe. It reads live quotes
# through the watchlist dashboard, so it uses the same curl-impersonate env as the other finance-api-cron jobs.

12 * * * * su - www-data -s /bin/bash -c "export LOG_CHANNEL=stdout; export LD_PRELOAD=/usr/local/lib/libcurl-impersonate-chrome.so; export CURL_IMPERSONATE=chrome116; cd [APP_PATH]/ && cpulimit -l 50 -- php artisan finance:peak-proximity-alerts --all-users >> [APP_PATH]/storage/logs/finance-api-cron.log 2>&1"

@reboot sleep 480 && su - www-data -s /bin/bash -c "export LOG_CHANNEL=stdout; export LD_PRELOAD=/usr/local/lib/libcurl-impersonate-chrome.so; export CURL_IMPERSONATE=chrome116; cd [APP_PATH]/ && cpulimit -l 50 -- php artisan finance:peak-proximity-alerts --all-users >> [APP_PATH]/storage/logs/finance-api-cron.log 2>&1"
#############
```

```bash
# Preview for one user without sending or recording
php artisan finance:peak-proximity-alerts --user-id=1 --dry-run

# All users with enabled alerts
php artisan finance:peak-proximity-alerts --all-users

# One uniform threshold N% for every window, or specific symbols only
php artisan finance:peak-proximity-alerts --user-id=1 --threshold=10
php artisan finance:peak-proximity-alerts --user-id=1 --symbols=AMD,ETH-EUR
```

**Off by default**: each user enables symbols at `/peak-proximity-alerts` (per symbol or in bulk, optionally until a
date). Env (all optional): `MYFINANCE2_PEAK_PROXIMITY_ENABLED` (true); `MYFINANCE2_PEAK_PROXIMITY_THRESHOLD_3M` /
`_6M` / `_1Y` / `_2Y` (2 / 5 / 8 / 10); `MYFINANCE2_PEAK_PROXIMITY_THRESHOLD_PCT` (5, fallback);
`MYFINANCE2_PEAK_PROXIMITY_EXIT_FOCUSED` (true: only a weak tier emails, see "Gate"; false: any window near peak);
`MYFINANCE2_PEAK_PROXIMITY_EMAIL_TO` (the alerts email, then the user's); `MYFINANCE2_PEAK_PROXIMITY_RSI_OVERBOUGHT`
(70) / `_RSI_OVERSOLD` (30); `MYFINANCE2_PEAK_PROXIMITY_REMINDER_DAYS` (7, fallback for the near-peak cadence);
`MYFINANCE2_PEAK_PROXIMITY_RANGE_REMINDER_DAYS` / `_TAKE_PROFIT_REMINDER_DAYS` / `_TIER_SIGNAL_REMINDER_DAYS`
(7); `MYFINANCE2_PEAK_PROXIMITY_TIER_SIGNAL_EMAILS` (none: the tier signals that email, comma-separated);
`MYFINANCE2_PEAK_PROXIMITY_RANGE_EMAILS` (`RANGE_LOW,RANGE_BUY`: the range-bound signals that email).

### finance:dip-buying-alerts

The inverse of peak-proximity: paces spending a fixed EUR dip-buying pool as the market falls. Damage control for cash
you keep anyway, not a promise to beat staying invested. One engine (`DipBuyingPlanService`) feeds the `/positions`
panel, this email, the settings page `/dip-buying-alerts` and the backtest `/dip-buying-backtest`.

- **Drawdown**: `max(VUSA.AS trailing-peak drawdown, your portfolio drawdown)`.
- **Ladder** (cumulative % of the pool deployed): 10%->30%, 15%->55%, 20%->75%, 25%->90%, 30%->100%.
- **Verdict**: "no dip yet" under 10%, then behind / on plan / ahead of plan (5pp tolerance). VUSA vs its 200-day MA
  is context only; a six-month stall backstop releases idle cash slowly.
- **Email** only on a state change (the band deepens, on plan to behind, the stall backstop activates); never "the
  trend turned".

```bash
sudo su
crontab -e

#############
# Purpose: Dip Buying Plan email when the plan's state changes (the band deepens, you cross from
# on-plan to behind, or the stall backstop activates). Checked hourly but throttled to one email per
# user per day, so running every hour is safe; it just surfaces a state change sooner instead of
# waiting for the close. Off by default; only users who enabled the feature AND the email channel on
# /dip-buying-alerts are processed, so a full run is a no-op for everyone else. The command is
# lightweight (reads the cached drawdown plan, no Yahoo API).

33 * * * * su - www-data -s /bin/bash -c "export LOG_CHANNEL=stdout; cd [APP_PATH]/ && cpulimit -l 50 -- php artisan finance:dip-buying-alerts --all-users >> [APP_PATH]/storage/logs/finance-api-cron.log 2>&1"

@reboot sleep 510 && su - www-data -s /bin/bash -c "export LOG_CHANNEL=stdout; cd [APP_PATH]/ && cpulimit -l 50 -- php artisan finance:dip-buying-alerts --all-users >> [APP_PATH]/storage/logs/finance-api-cron.log 2>&1"
#############
```

```bash
# Preview for one user without sending or recording
php artisan finance:dip-buying-alerts --user-id=1 --dry-run

# All users with the feature + email enabled
php artisan finance:dip-buying-alerts --all-users

# Self-validation backtest: replay your trades through the ladder (CLI mirror of /dip-buying-backtest)
php artisan finance:dip-buying-backtest --user-id=1
php artisan finance:dip-buying-backtest --user-id=1 --from=2025-01-01 --pool=10000
```

**Off by default**: set the pool and enable the feature and the email channel at `/dip-buying-alerts` (an advanced
JSON override replaces the default ladder). Env (all optional): `MYFINANCE2_DIP_BUYING_ENABLED` (true);
`MYFINANCE2_DIP_BUYING_TOLERANCE_PCT` (5); `MYFINANCE2_DIP_BUYING_PEAK_LOOKBACK_YEARS` (3);
`MYFINANCE2_DIP_BUYING_MA_TREND_PERIOD` (200); `MYFINANCE2_DIP_BUYING_STALL_BACKSTOP_MONTHS` (6);
`MYFINANCE2_DIP_BUYING_MIN_EPISODE_DD_PCT` (10); `MYFINANCE2_DIP_BUYING_MIN_DROP_PCT` (5: the depth from which the
backtest and its chart mark a drop); `MYFINANCE2_DIP_BUYING_RSI_NOTE_ENABLED` (false) with
`MYFINANCE2_DIP_BUYING_RSI_OVERSOLD_LEVEL` (30); `MYFINANCE2_DIP_BUYING_EMAIL_TO` (the alerts email, then the user's).
The plan is cached 2h; a settings change clears it, editing the ladder defaults or the drawdown formula needs a
`cache:clear`.

### finance:portfolio-peak-alerts

The portfolio-level counterpart on the way up: emails when the portfolio's EUR gain (`change_EUR`) or return on cost
(`changePercentage_EUR`) is near its rolling 6M / 1Y / 2Y high, a hint to take profit or rebalance. Read from the
stored daily overview chart; the VUSA.AS 2Y distance is context only.

- Each metric is enabled per user. Negative peaks work: EUR gain is measured against `|peak|` (peaks under
  `min_peak_abs_eur` skipped), return % on the index `1 + return/100`.
- Fires when any enabled (metric, window) pair is within its threshold (Return %: 1 / 3 / 5; EUR gain: 10 / 20 / 30),
  then every `reminder_days` (7) while it holds.
- The email and the "Current standing" card on `/portfolio-peak-alerts` show the full metric x window table (3M as
  context only, never fires) to help calibrate the thresholds.

```bash
sudo su
crontab -e

#############
# Purpose: Portfolio Peak Alert email when the portfolio's EUR gain or return on cost is near its
# rolling 6M/1Y/2Y high. Checked hourly but throttled to one email per user per day, so running
# every hour is safe; it just surfaces a trigger sooner. Off by default; only users who enabled the
# feature AND the email channel on /portfolio-peak-alerts are processed, so a full run is a no-op
# for everyone else. The command is lightweight (reads the cached overview chart series, no Yahoo API).

48 * * * * su - www-data -s /bin/bash -c "export LOG_CHANNEL=stdout; cd [APP_PATH]/ && cpulimit -l 50 -- php artisan finance:portfolio-peak-alerts --all-users >> [APP_PATH]/storage/logs/finance-api-cron.log 2>&1"

@reboot sleep 540 && su - www-data -s /bin/bash -c "export LOG_CHANNEL=stdout; cd [APP_PATH]/ && cpulimit -l 50 -- php artisan finance:portfolio-peak-alerts --all-users >> [APP_PATH]/storage/logs/finance-api-cron.log 2>&1"
#############
```

```bash
# Preview for one user without sending or recording
php artisan finance:portfolio-peak-alerts --user-id=1 --dry-run

# All users with the feature + email enabled
php artisan finance:portfolio-peak-alerts --all-users
```

**Off by default**: enable the feature, the email channel and the per-metric toggles at `/portfolio-peak-alerts`.
Env (all optional): `MYFINANCE2_PORTFOLIO_PEAK_ENABLED` (true); `MYFINANCE2_PORTFOLIO_PEAK_EUR_THRESHOLD_3M` / `_6M` /
`_1Y` / `_2Y` (5 / 10 / 20 / 30) and `MYFINANCE2_PORTFOLIO_PEAK_PCT_THRESHOLD_3M` / `_6M` / `_1Y` / `_2Y`
(0.5 / 1 / 3 / 5; 3M is a reference line only); `MYFINANCE2_PORTFOLIO_PEAK_MIN_PEAK_ABS_EUR` (1000);
`MYFINANCE2_PORTFOLIO_PEAK_REMINDER_DAYS` (7; `0` means every run that finds a trigger, still one email a day);
`MYFINANCE2_PORTFOLIO_PEAK_EMAIL_TO` (the alerts email, then the user's).


## Emails

Every email this package sends, and what sends it. Recipient: `MYFINANCE2_ALERTS_EMAIL_TO`, else the user's email
(the peak-proximity, dip buying and portfolio peak emails each take their own `..._EMAIL_TO` first, see above).

| Kind | Mailable | Sent by |
|------|----------|---------|
| `price-alert` | `PriceAlertTriggered` | `app:finance-api-cron` when an active price alert reaches its target |
| `price-alert-split` | `PriceAlertTriggered` | the same check, instead, when the target is so far from the price that a split is likely |
| `alert-created` | `PriceAlertCreated` | creating an alert, or a batch of suggested ones, on `/price-alerts` |
| `alert-paused` | `PriceAlertStateChanged` | pausing or resuming alerts on `/price-alerts` |
| `peak-proximity` | `PeakProximityAlert` | `finance:peak-proximity-alerts`: near peak, range-bound, take profit and tier signal |
| `portfolio-peak` | `PortfolioPeakAlert` | `finance:portfolio-peak-alerts` |
| `dip-buying` | `DipBuyingAlert` | `finance:dip-buying-alerts` |
| `split-applied` | `SplitApplied` | applying (or re-applying) a stock split on `/stock-splits` |
| `returns-missing-quotes` | `ReturnsMissingQuotes` | `app:finance-api-cron --refresh-returns` (see "Returns") |

**Samples.** `EmailSamples::builders($userId)` builds one sample of each kind above, for the host app to send and
check how they look in a mail client (laravel-admin's `emails:samples` does this, adding its own emails). Built from
the user's real data where that is read-only (the price alert at the live price, today's peak-proximity readings, the
portfolio peak and dip buying figures), else from sample figures (the split, the returns warning). Persists nothing:
no alert fires and no notification, inbox or throttle row is written, so the real alert crons are unaffected. A kind
no data reads that way today is skipped. The caller authenticates as the user (read-only) before building.


## Running tests

Both suites run from this package's directory, with its own dev dependencies (`composer update` here, never in the
app). Unit tests are pure; feature tests run a Laravel app with only this package (orchestra/testbench) on the
databases of the app it is installed in: its `.env` (or `MYFINANCE2_TEST_ENV_FILE`) gives the connections. Every
test rolls back, and a run that leaves rows behind fails (`tests/Support/LeftoverRowsExtension`).

```bash
cd vendor/ovidiuro/myfinance2
# php vendor/bin/phpunit --filter ChartsBuilderMetrics --testdox --debug
php vendor/bin/phpunit --testsuite Unit
php vendor/bin/phpunit --testsuite Feature
```
