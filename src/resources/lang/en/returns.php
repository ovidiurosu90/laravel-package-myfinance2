<?php

return [
    'titles' => [
        'dashboard'         => 'Returns',
        'year-selector'     => 'Select Year',
        'returns-overview'  => 'Returns Overview (All Years)',
    ],
    'labels' => [
        'dec31-value'          => 'End value (portfolio at Dec 31st)',
        'jan1-value'           => 'Start value (portfolio at Jan 1st)',
        'deposits'             => 'Deposits (Credits)',
        'withdrawals'          => 'Withdrawals (Debits)',
        'gross-dividends'      => 'Dividends (gross)',
        'stock-purchases'      => 'Purchases (stock buys)',
        'stock-sales'          => 'Sales (stock sells)',
        'actual-return'        => 'Return',
        'formula-explanation'  => 'Return = Dividends + End value – Start value – Purchases + Sales',
        'formula-explanation-with-dw' => 'Return = Dividends + End value – Start value – Purchases + Sales – Deposits + Withdrawals',
    ],
    'tooltips' => [
        'toggle-dw' => '<strong>D&amp;W: On</strong> — Deposits &amp; Withdrawals are counted'
            . ' towards the return (default). Deposits subtract; withdrawals add.'
            . '<br><strong>D&amp;W: Off</strong> — Deposits &amp; Withdrawals are excluded'
            . ' from the return calculation.',
        'toggle-cash' => '<strong>Cash: On</strong> — Start &amp; End portfolio values include'
            . ' cash balances (default).'
            . '<br><strong>Cash: Off</strong> — only securities positions are counted'
            . ' (cash excluded from the return calculation).',
        'formula-dw-on' => 'Deposits &amp; Withdrawals <strong>are</strong> counted towards the actual return.'
            . ' A deposit subtracts from your return (new capital added),'
            . ' a withdrawal adds to it (capital taken out).'
            . '<br><br>'
            . 'The field &quot;Purchases&quot; / &quot;Sales&quot; only tracks securities'
            . ' moving in and out of your portfolio, not cash moving between accounts.',
        'formula-dw-off' => 'Deposits &amp; Withdrawals are <strong>not</strong> counted towards the actual return.'
            . '<br><br>'
            . 'The field &quot;Purchases&quot; / &quot;Sales&quot; only tracks securities'
            . ' moving in and out of your portfolio, not cash moving between accounts.'
            . '<br><br><strong>Don\'t include:</strong> moving cash from your bank to'
            . ' your broker\'s cash account (or vice versa). That\'s just cash changing'
            . ' location; it\'s already captured under bank &amp; savings accounts.'
            . '<br><br><strong>Do include:</strong><br>'
            . '&bull; Actually buying or selling stocks/bonds/funds (the most common case)<br>'
            . '&bull; Securities entering your portfolio through gifts, inheritance,'
            . ' or transfers from another broker<br>'
            . '&bull; Proceeds from sales or bond redemptions'
            . '<br><br><strong>The simple rule:</strong> did securities change into cash,'
            . ' or cash into securities? That\'s what counts.'
            . ' Cash just moving between accounts doesn\'t.',
    ],
    'alerts' => [
        'title' => 'Override Reminders',
        'hint'  => 'These trades may require configuration overrides. Check the trades-private.php config file.',
    ],
];

