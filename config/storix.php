<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Storix\Support\FinancialYear;

return [
    'models' => [
        'container' => env('STORIX_CONTAINER_MODEL', 'Storix\\Models\\Container'),
        'dispatch' => env('STORIX_DISPATCH_MODEL', 'Storix\\Models\\Dispatch'),
        'dispatch_entry' => env('STORIX_DISPATCH_ENTRY_MODEL', 'Storix\\Models\\DispatchEntry'),
        'delivery_note' => env('STORIX_DELIVERY_NOTE_CLASS', 'App\\Models\\Sales\\DeliveryNote'),
        'user' => env('STORIX_USER_MODEL', 'App\\Models\\User'),
    ],

    'tables' => [
        'containers' => env('STORIX_CONTAINERS_TABLE', 'containers'),
        'dispatches' => env('STORIX_DISPATCHES_TABLE', 'dispatches'),
        'dispatch_entries' => env('STORIX_DISPATCH_ENTRIES_TABLE', 'dispatch_entries'),
        'delivery_notes' => env('STORIX_DELIVERY_NOTE_TABLE', 'delivery_notes'),
        'users' => env('STORIX_USER_TABLE', 'users'),
    ],

    'labels' => [
        'container' => env('STORIX_CONTAINER_LABEL', 'container'),
        'dispatch' => env('STORIX_DISPATCH_LABEL', 'dispatch'),
        'dispatch_entry' => env('STORIX_DISPATCH_ENTRY_LABEL', 'dispatch entry'),
    ],

    'financial_year_service_class' => env('STORIX_FINANCIAL_YEAR_SERVICE_CLASS', 'App\\Services\\FinancialYearService'),

    'delivery_note_query_modifier' => static function (Builder $query): Builder {
        $year = FinancialYear::selected();

        return $query
            ->whereNull('dispatched_at')
            ->where('state', 'approved')
            ->whereBetween('transaction_date', [
                $year?->start_date ?? today(),
                $year?->end_date ?? today(),
            ]);
    },

    'permissions' => [
        'register' => true,
        'guard_name' => 'web',
    ],
];
