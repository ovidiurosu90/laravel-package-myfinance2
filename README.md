# Laravel Package MyFinance2

Laravel package for managing my finances

- account
- currency
- ledger
- funding
- watchlist
- trades
- positions
- cash
- dividends
- timeline

## Installation instructions

```bash
sudo apt-get install php-intl php-dom php-mysql php-mbstring php-gd htop colordiff

mysql -uroot -p
    CREATE DATABASE [MYFINANCE2_DB_DATABASE];
    CREATE USER '[MYFINANCE2_DB_USERNAME]'@'localhost' IDENTIFIED WITH mysql_native_password BY '[MYFINANCE2_DB_PASSWORD]';
    GRANT ALL PRIVILEGES ON [MYFINANCE2_DB_DATABASE].* TO '[MYFINANCE2_DB_USERNAME]'@'localhost';

    -- For foreign key constraints
    GRANT SELECT ON [DB_DATABASE].users TO '[DB_USERNAME]'@'localhost';
    GRANT REFERENCES ON [DB_DATABASE].users TO '[DB_USERNAME]'@'localhost';
    FLUSH PRIVILEGES;

    exit

mysql -u[MYFINANCE2_DB_USERNAME] -p [MYFINANCE2_DB_DATABASE] # use [MYFINANCE2_DB_PASSWORD] set above


#NOTE Execute the following if the Database Migration was not already run in the main package
php artisan migrate --pretend
php artisan migrate
# php artisan migrate:rollback # If needed

#NOTE If there were database entries that didn't have user_id before, execute the following

mysql -u[MYFINANCE2_DB_USERNAME] -p [MYFINANCE2_DB_DATABASE] # use [MYFINANCE2_DB_PASSWORD] set above
    select * from `[DB_DATABASE]`.`users`;

    update `cash_balances` set user_id = [USER_ID] where user_id is null;
    update `dividends` set user_id = [USER_ID] where user_id is null;
    update `ledger_transactions` set user_id = [USER_ID] where user_id is null;
    update `trades` set user_id = [USER_ID] where user_id is null;
    update `watchlist_symbols` set user_id = [USER_ID] where user_id is null;

```

### Get market status (used by /positions)

```bash
sudo chmod 775 src/scripts/market_status.py
sudo chown :www-data src/scripts/market_status.py

sudo apt-get update
sudo apt install python3-pip
# pip install pandas-market-calendars
sudo pip install pandas-market-calendars

# Test
./src/scripts/market_status.py 'LSE'
```

### Enable finance-api-cron for better performance

```bash
cd ~/Repositories/laravel-admin/
>storage/logs/finance-api-cron.log
chown :www-data storage/logs/finance-api-cron.log
ls -la storage/logs/finance-api-cron.log
tail -f storage/logs/finance-api-cron.log

sudo su
crontab -e

#############
# We need two jobs to run every 30 seconds
* * * * * su - www-data -s /bin/bash -c "export LOG_CHANNEL=stdout; cd [USER_HOME]/Repositories/laravel-admin/ && php artisan app:finance-api-cron >> [USER_HOME]/Repositories/laravel-admin/storage/logs/finance-api-cron.log 2>&1"
* * * * * ( sleep 30; su - www-data -s /bin/bash -c "export LOG_CHANNEL=stdout; cd [USER_HOME]/Repositories/laravel-admin/ && php artisan app:finance-api-cron >> [USER_HOME]/Repositories/laravel-admin/storage/logs/finance-api-cron.log 2>&1" )
#############
```

