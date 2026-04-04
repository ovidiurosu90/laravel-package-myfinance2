<?php

return [
    'titles' => [
        'dashboard' => 'Price Alerts',
    ],
    'items-table' => [
        'caption' => '{1} :count alert|[2,*] :count alerts',
        'none'    => 'No Price Alerts',
    ],
    'flash-messages' => [
        'item-created'       => 'Successfully created Alert with id :id',
        'item-updated'       => 'Successfully updated Alert with id :id',
        'item-deleted'       => 'Alert with id :id has been deleted',
        'alert-paused'       => 'Alert #:id has been paused',
        'alert-resumed'      => 'Alert #:id has been resumed (now Active)',
        'invalid-transition'     => 'Cannot perform this action on Alert #:id in status :status',
        'suggestions-created'    => '{1} 1 alert suggestion created from open positions|[2,*] :count alert suggestions created from open positions',
        'suggestions-none'       => 'No new suggestions — open positions already have alerts, or no 52W high signal above current price',
    ],
    'tooltips' => [
        'pause-alert'  => 'Pause Alert (stop evaluating)',
        'resume-alert' => 'Resume Alert (set back to Active)',
    ],
    'modals' => [
        'pause_modal_title'   => 'Pause Alert #:id',
        'pause_modal_message' => 'Are you sure you want to pause Alert #:id?',
        'resume_modal_title'  => 'Resume Alert #:id',
        'resume_modal_message' => 'Are you sure you want to resume Alert #:id?',
    ],
    'buttons' => [
        'pause'  => '<i class="fa fa-fw fa-pause" aria-hidden="true"></i> Pause',
        'resume' => '<i class="fa fa-fw fa-play" aria-hidden="true"></i> Resume',
    ],
    'forms' => [
        'item-form' => [
            'symbol' => [
                'label'       => 'Symbol',
                'placeholder' => 'Select or enter symbol',
            ],
            'alert_type' => [
                'label'       => 'Alert Type',
                'placeholder' => 'Select Alert Type',
            ],
            'target_price' => [
                'label'       => 'Target Price',
                'placeholder' => 'Input Target Price',
            ],
            'trade_currency' => [
                'label'       => 'Trade Currency',
                'placeholder' => 'Select Trade Currency',
            ],
            'status' => [
                'label'       => 'Status',
                'placeholder' => 'Select Status',
            ],
            'notes' => [
                'label'       => 'Notes',
                'placeholder' => 'Optional notes (e.g. "3% below 2Y high of $227.30")',
            ],
            'expires_at' => [
                'label' => 'Expires At (optional)',
            ],
            'buttons' => [
                'save-item' => [
                    'name'    => 'Save Alert',
                    'sr-icon' => 'Save Alert Icon',
                ],
                'update-item' => [
                    'name'    => 'Save Alert Changes',
                    'sr-icon' => 'Save Alert Changes Icon',
                ],
            ],
        ],
    ],
];
