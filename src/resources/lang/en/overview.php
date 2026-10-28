<?php

return [
    'titles' => [
        'dashboard' => 'Overview',
    ],
    'tooltips' => [
        'transferred-funding' => 'Virtual account to explain the'
            . ' funding for transferred positions.'
            . '<br>Identified by keyword'
            . ' &quot;:keyword&quot; in the account description.',
        'transferred-year' => 'Includes :amount from'
            . ' transferred positions',
        'transferred-account' => 'Includes :amount (:amount_eur)'
            . ' from transferred positions',
        'transferred-account-eur' => 'Includes :amount'
            . ' from transferred positions',
        'transferred-symbol' => ':annotation (:amount)',
    ],
    'cards' => [
        'funding-sources' => [
            'title'    => 'Funding',
            'no-items' => 'No funding source accounts found.',
            'info'     => '<b>The money you put in</b> from your own accounts (bank, salary, savings), minus what'
                . ' you took back out. A negative balance means money left that account to be invested.'
                . '<br><br><b>Example:</b> you send 10,000 &euro; from your bank to a broker, then withdraw'
                . ' 2,000 &euro; back: the bank shows -8,000 &euro;.'
                . '<br><br>Moves between investment accounts (broker to broker) don&#039;t count here, and'
                . ' neither do gains or losses: they show up in Investment and Gains Per Year.'
                . ' Balance &euro; converts at today&#039;s rate.',
        ],
        'intermediary-accounts' => [
            'title'    => 'Intermediary Accounts',
            'no-items' => 'No intermediary accounts found.',
        ],
        'investment-accounts' => [
            'title'    => 'Investment',
            'no-items' => 'No investment accounts found.',
            'info'     => '<b>What each investment account holds right now.</b>'
                . '<br><b>Cost:</b> what you paid for the shares you still own.'
                . '<br><b>MValue:</b> what those shares are worth today.'
                . '<br><b>Gain:</b> MValue minus Cost; not sold yet, so it can still change.'
                . '<br><b>Cash:</b> money not invested (the latest cash balance; savings accounts use their'
                . ' funding balance).'
                . '<br><b>Balance:</b> MValue + Cash.'
                . '<br><br><b>Example:</b> you bought shares for 5,000 &euro; that are worth 6,000 &euro; today,'
                . ' and 1,000 &euro; sits uninvested: Cost 5,000 &euro;, MValue 6,000 &euro;, Gain +1,000 &euro;,'
                . ' Cash 1,000 &euro;, Balance 7,000 &euro;.'
                . '<br><br>Sold shares leave this table and their result moves to Gains Per Year; an account'
                . ' you emptied shows 0.',
        ],
        'other-accounts' => [
            'title'    => 'Other Accounts',
            'no-items' => 'No other accounts found.',
        ],
        'uncategorized-accounts' => [
            'title'    => 'Uncategorized Accounts',
            'no-items' => 'No uncategorized accounts found.',
        ],
        'gains-per-year' => [
            'title'    => 'Gains Per Year',
            'no-items' => 'No gains data found.',
            'info'     => '<b>What you earned or lost when you sold, plus dividends</b>, grouped by the year it'
                . ' happened.'
                . '<br><b>Stock gain:</b> what you sold for minus what you paid, fees included.'
                . '<br><b>Dividends:</b> what the stocks paid you.'
                . '<br><br><b>Example:</b> you buy 10 shares at 100 &euro; in 2024 and sell them at 150 &euro; in'
                . ' 2025: +500 &euro; under 2025, not 2024. Sell them at 80 &euro; instead: -200 &euro; under'
                . ' 2025.'
                . '<br><br>Shares you still own aren&#039;t here (see Investment). Amounts in another currency'
                . ' convert to &euro; at the year-end rate (today&#039;s rate for the current year).',
        ],
        'top-winners' => [
            'title'    => 'Top Winners',
            'no-items' => 'No data available.',
            'info'     => '<b>The accounts and stocks that earned you the most across all years:</b> stock gains'
                . ' from sales plus dividends, in &euro;, as in Gains Per Year.'
                . '<br><br><b>Example:</b> you sold stock A for +3,000 &euro; in 2023 and +2,000 &euro; in 2025,'
                . ' and it paid 500 &euro; in dividends: A counts +5,500 &euro;.'
                . '<br><br>Shares not sold yet don&#039;t count. Hover a row for its yearly breakdown.',
        ],
        'transferred-positions' => [
            'title' => 'Transferred Positions Analysis',
        ],
        'reconciliation' => [
            'title' => 'Reconciliation',
        ],
    ],
];

