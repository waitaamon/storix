# Storix

Storix is a production-grade Filament v4 plugin for reusable container lifecycle tracking in ERP systems.

## Features

- Container master data management with UUID primary keys and soft deletes.
- Full dispatch/return lifecycle tracking with condition-based status flow.
- Postgres-optimized schema (UUIDs, JSONB, indexes, constraints).
- Filament resources for containers and dispatches.
- Dispatch history relation manager on containers.
- Filament importers:
  - Container import (create/upsert by serial)
  - Dispatch import (create)
  - Return import (update existing dispatch)
- Filament exporters for containers and dispatches.
- Spatie permission integration with automatic Storix permission registration.
- Policy classes for container and dispatch authorization.
- Lifecycle service + DTO for transactional dispatch and return handling.
- Pest v4 test suite covering CRUD, lifecycle, import/export definitions, and policies.
- Bonus widgets:
  - Container utilization
  - Damage rate
  - Customer/container aging
  - Lost financial exposure

## Installation

```bash
composer require waitaamon/storix
php artisan vendor:publish --tag=storix-config
php artisan migrate
```

## Filament Panel Registration

```php
use WaitAmon\Storix\StorixPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugins([
        StorixPlugin::make(),
    ]);
}
```

## Permissions

Storix auto-registers the following permissions (guard: `web` by default):

- `viewAny.containers`
- `view.containers`
- `create.containers`
- `update.containers`
- `delete.containers`
- `restore.containers`
- `forceDelete.containers`
- `viewAny.dispatches`
- `view.dispatches`
- `create.dispatches`
- `update.dispatches`
- `delete.dispatches`
- `restore.dispatches`
- `forceDelete.dispatches`
- `receive.containers`

You can also seed explicitly:

```bash
php artisan db:seed --class="WaitAmon\\Storix\\Database\\Seeders\\StorixPermissionSeeder"
```

## Imports & Exports

### Imports

- Containers: create/update by serial.
- Dispatches: create dispatch records.
- Returns: update existing dispatch by dispatch UUID.

Imports enforce validation and provide row-level failure reporting through Filament import jobs.

### Exports

- Container bulk export.
- Dispatch bulk export.

Exports use Filament export actions and exporter classes.

## Data Integrity Notes

- UUID primary keys on Storix entities.
- Soft delete support on containers and dispatches.
- Foreign keys with explicit update/delete behavior.
- Indexed high-frequency query columns (`container_id`, `customer_id`, `dispatched_at`, `return_date`).
- JSONB metadata columns for ERP extensibility and analytics.

## Tests

```bash
composer test
```
