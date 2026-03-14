<?php

return [
    'titles' => [
        'dashboard' => 'Orders',
    ],
    'items-table' => [
        'caption' => '{1} :count order|[2,*] :count orders',
        'none'    => 'No Orders',
    ],
    'flash-messages' => [
        'order-placed'         => 'Successfully Placed Order with id :id',
        'order-filled'         => 'Successfully Filled Order with id :id',
        'order-expired'        => 'Order with id :id marked as Expired',
        'order-cancelled'      => 'Order with id :id has been Cancelled',
        'order-reopened'       => 'Order #:id reopened (status reset to Placed)',
        'trade-linked'         => 'Trade #:trade_id linked to Order #:id',
        'trade-unlinked'       => 'Trade unlinked from Order #:id',
        'invalid-transition'   => 'Cannot perform this action on Order #:id in status :status',
        'fill-required-fields' => 'Please fill in Account, Quantity, and Limit Price before placing Order #:id',
        'not-editable'         => 'Order #:id cannot be edited (status: :status)',
    ],
    'tooltips' => [
        'place-order'   => 'Place Order (DRAFT → PLACED)',
        'fill-order'    => 'Fill Order (PLACED → FILLED)',
        'expire-order'  => 'Mark as Expired (PLACED → EXPIRED)',
        'cancel-order'  => 'Cancel Order',
        'reopen-order'  => 'Reopen Order (revert to Placed)',
        'link-trade'    => 'Link to a Trade',
        'unlink-trade'  => 'Unlink Trade',
    ],
    'modals' => [
        'place_modal_title'    => 'Place Order with id :id',
        'place_modal_message'  => 'Are you sure you want to place Order with id :id?',
        'expire_modal_title'   => 'Expire Order with id :id',
        'expire_modal_message' => 'Are you sure you want to mark Order with id :id as Expired?',
        'cancel_modal_title'   => 'Cancel Order with id :id',
        'cancel_modal_message' => 'Are you sure you want to cancel Order with id :id?',
        'reopen_modal_title'   => 'Reopen Order with id :id',
        'reopen_modal_message' => 'Are you sure you want to reopen Order #:id? Status will be reset to Placed.',
    ],
    'buttons' => [
        'place'        => 'Place <i class="fa fa-fw fa-paper-plane" aria-hidden="true"></i>',
        'fill'         => 'Fill <i class="fa fa-fw fa-check" aria-hidden="true"></i>',
        'expire'       => 'Expire <i class="fa fa-fw fa-clock-o" aria-hidden="true"></i>',
        'cancel'       => 'Cancel <i class="fa fa-fw fa-ban" aria-hidden="true"></i>',
        'reopen'       => 'Reopen <i class="fa fa-fw fa-undo" aria-hidden="true"></i>',
        'link-trade'   => 'Link Trade <i class="fa fa-fw fa-link" aria-hidden="true"></i>',
        'unlink-trade' => 'Unlink <i class="fa fa-fw fa-chain-broken" aria-hidden="true"></i>',
    ],
    'forms' => [
        'item-form' => [
            'symbol' => [
                'label'       => 'Symbol',
                'placeholder' => 'Select or enter symbol',
            ],
            'action' => [
                'label'       => 'Action',
                'placeholder' => 'Select Action',
            ],
            'status' => [
                'label'       => 'Status',
                'placeholder' => 'Select Status',
            ],
            'account' => [
                'label'       => 'Account',
                'placeholder' => 'Select Account',
            ],
            'trade_currency' => [
                'label'       => 'Trade Currency',
                'placeholder' => 'Select Trade Currency',
            ],
            'exchange_rate' => [
                'label'       => 'Exchange Rate',
                'placeholder' => 'Input Exchange Rate',
            ],
            'quantity' => [
                'label'       => 'Quantity',
                'placeholder' => 'Input Quantity',
            ],
            'limit_price' => [
                'label'       => 'Limit Price',
                'placeholder' => 'Input Limit Price',
            ],
            'placed_at' => [
                'label' => 'Placed At',
            ],
            'description' => [
                'label'       => 'Description / Rationale',
                'placeholder' => 'Input description or rationale',
            ],
            'buttons' => [
                'save-item' => [
                    'name'    => 'Save Order',
                    'sr-icon' => 'Save Order Icon',
                ],
                'update-item' => [
                    'name'    => 'Save Order Changes',
                    'sr-icon' => 'Save Order Changes Icon',
                ],
            ],
        ],
    ],
];
