<?php

return [
    'titles' => [
        'dashboard' => 'Stock Splits',
    ],
    'items-table' => [
        'caption' => '{1} :count split|[2,*] :count splits',
        'none'    => 'No Stock Splits recorded',
    ],
    'flash-messages' => [
        'split-recorded'  => 'Split :ratio for :symbol recorded. :trades trade(s) updated, :alerts alert(s) adjusted.',
        'duplicate-split' => 'A split for :symbol on :date has already been recorded.',
    ],
    'forms' => [
        'item-form' => [
            'symbol' => [
                'label'       => 'Symbol',
                'placeholder' => 'Select or enter symbol',
            ],
            'split_date' => [
                'label' => 'Split Date',
            ],
            'ratio_numerator' => [
                'label'       => 'New Shares (e.g. 25 for a 25:1 split)',
                'placeholder' => '25',
            ],
            'ratio_denominator' => [
                'label'       => 'Old Shares (always 1 for forward splits)',
                'placeholder' => '1',
            ],
            'notes' => [
                'label'       => 'Notes (optional)',
                'placeholder' => 'e.g. link to announcement',
            ],
            'buttons' => [
                'save-item' => [
                    'name'    => 'Record Split & Apply',
                    'sr-icon' => 'Record Split Icon',
                ],
            ],
        ],
    ],
];
