<?php

declare(strict_types=1);

return [
    'customer_model' => 'App\\Models\\Customer',

    'customer_table' => 'customers',

    'user_model' => env('AUTH_MODEL', 'App\\Models\\User'),

    'users_table' => 'users',

    'containers_table' => 'containers',

    'dispatches_table' => 'dispatches',

    'register_permissions' => true,

    'permissions' => [
        'guard_name' => config('auth.defaults.guard', 'web'),
    ],
];
