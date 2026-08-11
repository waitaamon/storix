<?php

declare(strict_types=1);

use Storix\Support\DefaultCustomerQueryModifier;
use Storix\Support\DefaultDeliveryNoteQueryModifier;

return [
    'models' => [
        'container' => env('STORIX_CONTAINER_MODEL', 'Storix\\Models\\Container'),
        'dispatch' => env('STORIX_DISPATCH_MODEL', 'Storix\\Models\\Dispatch'),
        'dispatch_entry' => env('STORIX_DISPATCH_ENTRY_MODEL', 'Storix\\Models\\DispatchEntry'),
        'container_return' => env('STORIX_CONTAINER_RETURN_MODEL', 'Storix\\Models\\ContainerReturn'),
        'container_return_entry' => env('STORIX_CONTAINER_RETURN_ENTRY_MODEL', 'Storix\\Models\\ContainerReturnEntry'),
        'container_movement' => env('STORIX_CONTAINER_MOVEMENT_MODEL', 'Storix\\Models\\ContainerMovement'),
        'customer' => env('STORIX_CUSTOMER_MODEL', 'App\\Models\\Accounts\\Account'),
        'delivery_note' => env('STORIX_DELIVERY_NOTE_CLASS', 'App\\Models\\Sales\\DeliveryNote'),
        'user' => env('STORIX_USER_MODEL', 'App\\Models\\User'),
    ],

    'tables' => [
        'containers' => env('STORIX_CONTAINERS_TABLE', 'storix_containers'),
        'dispatches' => env('STORIX_DISPATCHES_TABLE', 'storix_dispatches'),
        'dispatch_entries' => env('STORIX_DISPATCH_ENTRIES_TABLE', 'storix_dispatch_entries'),
        'container_returns' => env('STORIX_CONTAINER_RETURNS_TABLE', 'storix_container_returns'),
        'container_return_entries' => env('STORIX_CONTAINER_RETURN_ENTRIES_TABLE', 'storix_container_return_entries'),
        'container_movements' => env('STORIX_CONTAINER_MOVEMENTS_VIEW', 'storix_container_movements'),
        'customers' => env('STORIX_CUSTOMER_TABLE', 'customers'),
        'delivery_notes' => env('STORIX_DELIVERY_NOTE_TABLE', 'delivery_notes'),
        'users' => env('STORIX_USER_TABLE', 'users'),
    ],

    'labels' => [
        'container' => env('STORIX_CONTAINER_LABEL', 'container'),
        'dispatch' => env('STORIX_DISPATCH_LABEL', 'dispatch'),
        'dispatch_entry' => env('STORIX_DISPATCH_ENTRY_LABEL', 'dispatch entry'),
        'container_return' => env('STORIX_CONTAINER_RETURN_LABEL', 'container return'),
        'container_return_entry' => env('STORIX_CONTAINER_RETURN_ENTRY_LABEL', 'container return entry'),
        'container_movement' => env('STORIX_CONTAINER_MOVEMENT_LABEL', 'container movement'),
    ],

    'financial_year_service_class' => env('STORIX_FINANCIAL_YEAR_SERVICE_CLASS', 'App\\Services\\FinancialYearService'),

    'customer_query_modifier' => env('STORIX_CUSTOMER_QUERY_MODIFIER', DefaultCustomerQueryModifier::class),

    'delivery_note_query_modifier' => env('STORIX_DELIVERY_NOTE_QUERY_MODIFIER', DefaultDeliveryNoteQueryModifier::class),

    'permissions' => [
        'guard_name' => 'web',
    ],

    'cross_return_reconciliation' => [
        'report_directory' => env(
            'STORIX_CROSS_RETURN_RECONCILIATION_REPORT_DIRECTORY',
            storage_path('logs/storix/cross-return-reconciliation'),
        ),
        'chunk_size' => (int) env('STORIX_CROSS_RETURN_RECONCILIATION_CHUNK_SIZE', 500),
        'deadlock_retries' => (int) env('STORIX_CROSS_RETURN_RECONCILIATION_DEADLOCK_RETRIES', 3),
        'schedule' => [
            'enabled' => env('STORIX_CROSS_RETURN_RECONCILIATION_SCHEDULE_ENABLED', true),
            'timezone' => env(
                'STORIX_CROSS_RETURN_RECONCILIATION_SCHEDULE_TIMEZONE',
                'Africa/Nairobi',
            ),
        ],
    ],
];
