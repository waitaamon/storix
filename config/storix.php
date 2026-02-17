<?php

declare(strict_types=1);

return [
    'models' => [
        'container' => env('STORIX_CONTAINER_MODEL', 'Storix\\Models\\Container'),
        'dispatch' => env('STORIX_DISPATCH_MODEL', 'Storix\\Models\\Dispatch'),
        'dispatch_entry' => env('STORIX_DISPATCH_ENTRY_MODEL', 'Storix\\Models\\DispatchEntry'),
        'customer' => env('STORIX_CUSTOMER_CLASS', 'App\\Models\\Accounts\\Account'),
        'user' => env('STORIX_USER_MODEL', 'App\\Models\\User'),
    ],

    'tables' => [
        'containers' => env('STORIX_CONTAINERS_TABLE', 'containers'),
        'dispatches' => env('STORIX_DISPATCHES_TABLE', 'dispatches'),
        'dispatch_entries' => env('STORIX_DISPATCH_ENTRIES_TABLE', 'dispatch_entries'),
        'customers' => env('STORIX_CUSTOMER_TABLE', 'accounts'),
        'users' => env('STORIX_USER_TABLE', 'users'),
    ],

    'labels' => [
        'container' => env('STORIX_CONTAINER_LABEL', 'container'),
        'dispatch' => env('STORIX_DISPATCH_LABEL', 'dispatch'),
    ],

    'register_permissions' => true,

    'permissions' => [
        'guard_name' => 'web',
    ],
];
