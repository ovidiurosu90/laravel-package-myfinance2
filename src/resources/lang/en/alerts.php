<?php

return [
    'titles' => [
        'dashboard' => 'Price Alerts',
    ],
    // The alert an open order created and keeps in sync (PriceAlert::linkedOrder()).
    'linked-order' => [
        'title'          => 'Linked to order #:id',
        'title-tooltip'  => 'Order #:id (:action :symbol at :price) keeps this alert in sync: it updates the'
            . ' alert when its limit price, action or symbol changes, and deletes it when filled or expired.',
        // :order is the link to the order ('order' below), :fields the changed fields ('fields' below).
        'order'          => 'order #:id',
        'warning'        => 'Attention: changing :fields breaks the link to :order.',
        'warning-detail' => "The order will stop updating this alert and won't delete it when filled or expired.",
        'reset'          => 'Reset',
        'reset-tooltip'  => 'Restore the linked symbol, alert type, status, target and expiry',
        'confirm-title'  => 'Break the link to :order?',
        'confirm-accept' => 'Save anyway',
        'confirm-back'   => 'Go back',
        'fields'         => [
            'symbol'       => 'the symbol',
            'alert_type'   => 'the alert type',
            'status'       => 'the status',
            'target_mode'  => 'the target mode',
            'target_price' => 'the target price',
            'expires_at'   => 'the expiry',
        ],
    ],
    'items-table' => [
        'caption' => '{1} :count alert|[2,*] :count alerts',
        'none'    => 'No Price Alerts',
    ],
    'flash-messages' => [
        'item-created'       => 'Successfully created Alert with id :id',
        'item-updated'       => 'Successfully updated Alert with id :id',
        'item-unlinked'      => '. It no longer follows order #:order',
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
            'target_mode' => [
                'label' => 'Target',
            ],
            'relative_target' => [
                'label'        => 'Target = closing high / low ± offset',
                'sign-tooltip' => 'A high is only ever lowered and a low only ever raised: an offset above a'
                    . ' high would move up with every new high and could never be reached.',
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
