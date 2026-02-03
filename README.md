# Filament Storix Plugin

ERP-grade Filament plugin for tracking reusable container lifecycle:

- Container registration
- Dispatch to customers
- Return tracking with condition status
- Excel-style import actions (dispatch + returns)
- Export actions (containers, dispatch history, return history)
- Dashboard widgets (currently with customers, overdue returns, dispatch vs return trend)

## Requirements

- PHP 8.3+
- Laravel 12+
- Filament 4+
- Livewire 3+

## Installation

```bash
composer require waitaamon/storix
```

Publish config (optional):

```bash
php artisan vendor:publish --tag=storix-config
```

Run migrations:

```bash
php artisan migrate
```

(Optional) seed sample containers:

```bash
php artisan db:seed --class="Storix\\Database\\Seeders\\StorixSeeder"
```

## Register Plugin In Filament Panel

```php
<?php

use Filament\Panel;
use Storix\StorixPlugin;

return Panel::make()
    ->plugins([
        StorixPlugin::make(),
    ]);
```

## Configuration

`config/storix.php`

```php
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
];
```

## Resource UX Behavior

### Containers

- CRUD with soft deletes
- Serial uniqueness enforced
- Active/inactive toggle
- Export action

### Dispatches

- Customer lookup with configurable search columns
- Multi-select container search (`serial` or `name`)
- Only active + currently available containers shown
- Import + export actions

### Returns

- Customer lookup with configurable search columns
- Selecting customer auto-fills open dispatched containers
- Condition status per returned container
- Import + export actions

## Import Columns

### Dispatch import

- `container_serial`
- `customer_name`
- `sale_order_code`
- `dispatch_date`
- `notes` (optional)

### Return import

- `container_serial`
- `return_date`
- `condition_status`
- `notes` (optional)

## Dashboard Widgets

- `Containers With Customers`
- `Overdue Returns`
- `Dispatch vs Return Trend (14 days)`

## Tests

```bash
composer install
vendor/bin/pest
```

## Code Quality

```bash
vendor/bin/pint
vendor/bin/phpstan analyse
```
