<?php

declare(strict_types=1);

return [
    'models' => [
        'container' => env('STORIX_CONTAINER_MODEL', 'Storix\\Models\\Container'),
        'dispatch' => env('STORIX_DISPATCH_MODEL', 'Storix\\Models\\Dispatch'),
        'dispatch_entry' => env('STORIX_DISPATCH_ENTRY_MODEL', 'Storix\\Models\\DispatchEntry'),
        'customer' => env('STORIX_CUSTOMER_CLASS', 'App\\Models\\Accounts\\Account'),
        'delivery_note' => env('STORIX_DELIVERY_NOTE_CLASS', 'App\\Models\\Sales\\DeliveryNote'),
        'user' => env('STORIX_USER_MODEL', 'App\\Models\\User'),
    ],

    'tables' => [
        'containers' => env('STORIX_CONTAINERS_TABLE', 'containers'),
        'dispatches' => env('STORIX_DISPATCHES_TABLE', 'dispatches'),
        'dispatch_entries' => env('STORIX_DISPATCH_ENTRIES_TABLE', 'dispatch_entries'),
        'customers' => env('STORIX_CUSTOMER_TABLE', 'accounts'),
        'delivery_notes' => env('STORIX_DELIVERY_NOTE_TABLE', 'delivery_notes'),
        'users' => env('STORIX_USER_TABLE', 'users'),
    ],

    'labels' => [
        'container' => env('STORIX_CONTAINER_LABEL', 'container'),
        'dispatch' => env('STORIX_DISPATCH_LABEL', 'dispatch'),
    ],

    'customer_query_modifier' => null,

    'permissions' => [
        'register' => true,
        'guard_name' => 'web',
    ],
];
