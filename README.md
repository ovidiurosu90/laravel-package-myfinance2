# Laravel Package MyFinance2

Laravel package for managing my finances

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
php artisan migrate --pretend
php artisan migrate
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

