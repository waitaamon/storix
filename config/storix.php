<?php

declare(strict_types=1);

return [
    'customer_model' => env('STORIX_CUSTOMER_CLASS', 'App\\Models\\Accounts\\Account'),

    'customer_table' => env('STORIX_CUSTOMER_TABLE', 'accounts'),

    'user_model' => env('STORIX_USER_MODEL', 'App\\Models\\User'),

    'users_table' => env('STORIX_USER_TABLE', 'users'),

    'containers_table' => 'containers',

    'dispatches_table' => 'dispatches',

    'register_permissions' => true,

    'permissions' => [
        'guard_name' => 'web',
    ],
];
