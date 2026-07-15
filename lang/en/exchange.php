<?php

return [
    'side' => [
        'buy' => 'Buy',
        'sell' => 'Sell',
    ],
    'type' => [
        'limit' => 'Limit',
        'market' => 'Market',
    ],
    'status' => [
        'open' => 'Open',
        'partially_filled' => 'Partially Filled',
        'filled' => 'Filled',
        'cancelled' => 'Cancelled',
    ],
    'transfer_status' => [
        'pending' => 'Pending',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ],
    'errors' => [
        'insufficient_balance' => 'Insufficient :asset balance.',
        'below_minimum' => 'Order size is below the minimum (:minimum).',
        'empty_book' => 'No matching orders on the other side. Market order cannot be placed.',
        'market_not_found' => 'Market :symbol was not found.',
        'order_not_found' => 'Order not found.',
        'market_inactive' => 'This market is currently closed for trading.',
        'order_not_cancelable' => 'This order cannot be cancelled.',
        'invalid_price_quantity' => 'Price and quantity must be greater than zero.',
        'invalid_quote_amount' => 'Order amount must be greater than zero.',
    ],
    'flash' => [
        'order_placed' => 'Your order has been placed.',
        'order_cancelled' => 'Order cancelled.',
        'deposit_requested' => 'Your deposit is being processed.',
        'withdrawal_requested' => 'Your withdrawal is being processed.',
        'market_updated' => 'Market settings updated.',
    ],
];
