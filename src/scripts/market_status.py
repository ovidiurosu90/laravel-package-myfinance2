#!/usr/bin/python3

# Get market status
# e.g.
# $ ./market_status.py NYSE
#    CLOSED 1617629400 1617652800
#    status open_timestamp close_timestamp

import sys
import datetime
import pandas as pd
import pandas_market_calendars as mcal
import numpy as np

if len(sys.argv) < 2:
    print('Missing argument market name')
    sys.exit()
name = str(sys.argv[1]).strip()

def get_unixtime(dt64):
    return dt64.astype('datetime64[s]').astype('int')

# https://pandas-market-calendars.readthedocs.io/en/latest/calendars.html
# https://github.com/quantopian/trading_calendars#calendar-support
# CHECK https://www.tradinghours.com/markets/nyse/hours
def main(name):
    calendar = mcal.get_calendar(name)
    startDate = datetime.date.today()
    endDate = datetime.date.today() + datetime.timedelta(days=7)
    schedule = calendar.schedule(start_date=startDate, end_date=endDate, tz='Europe/Amsterdam')

    openTime = schedule['market_open'].values[0]
    closeTime = schedule['market_close'].values[0]

    if np.datetime64('now') > closeTime:
        openTime = schedule['market_open'].values[1]
        closeTime = schedule['market_close'].values[1]

    isOpen = False
    try:
        isOpen = calendar.open_at_time(schedule, pd.Timestamp.now(tz='Europe/Amsterdam'))
    except ValueError as error:
        if str(error) == 'The provided timestamp is not covered by the schedule':
            isOpen = False
        else:
            print('%s %s' %('UNKNOWN', 'Something went wrong: ' + str(error)))
            return
    except:
        print('%s %s' %('UNKNOWN', 'Something went wrong'))
        return

    status = 'CLOSED'
    if isOpen:
        status = 'OPEN'
    openTimestamp = get_unixtime(openTime)
    closeTimestamp = get_unixtime(closeTime)
    print('%s %i %i' %(status, openTimestamp, closeTimestamp))


main(name)

