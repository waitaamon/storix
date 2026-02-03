<?php

declare(strict_types=1);

return [
    'customer_model' => App\Models\Customer::class,
    'user_model' => App\Models\User::class,
    'customer_table' => 'customers',
    'user_table' => 'users',
    'customer_title_attribute' => 'name',
    'customer_search_columns' => ['name'],
    'overdue_after_days' => 30,

    'tables' => [
        'containers' => 'containers',
        'dispatches' => 'container_dispatches',
        'dispatch_items' => 'container_dispatch_items',
        'returns' => 'container_returns',
        'return_items' => 'container_return_items',
    ],

    'return_condition_statuses' => [
        'excellent',
        'good',
        'damaged',
        'lost',
    ],
];
