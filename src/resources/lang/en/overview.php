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
        ],
        'intermediary-accounts' => [
            'title'    => 'Intermediary Accounts',
            'no-items' => 'No intermediary accounts found.',
        ],
        'investment-accounts' => [
            'title'    => 'Investment',
            'no-items' => 'No investment accounts found.',
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
        ],
        'top-winners' => [
            'title'    => 'Top Winners',
            'no-items' => 'No data available.',
        ],
        'transferred-positions' => [
            'title' => 'Transferred Positions Analysis',
        ],
    ],
];

