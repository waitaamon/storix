# Storix

Storix is a Filament v4 plugin for reusable container lifecycle tracking.

## Highlights

- Container management with soft deletes (`name`, `serial`, active state, description, metadata).
- Dispatch and return lifecycle tracking with status calculation (`Dispatched`, `Returned Good`, `Returned Damaged`, `Lost`).
- Filament resources for containers and dispatches.
- Relation manager for container dispatch history.
- Native Filament imports and exports.
- Spatie permission integration with automatic permission registration.
- Dashboard widgets for utilization, damage rate, aging, and lost exposure.
- Pest test suite for CRUD, lifecycle, import/export definitions, and policy checks.

## Requirements

- PHP 8.5+
- Laravel 12+
- Filament 4+

## Installation

```bash
composer require waitaamon/storix
php artisan vendor:publish --tag=storix-config
php artisan migrate
```

## Register In Filament Panel

```php
use Filament\Panel;
use Storix\StorixPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugins([
        StorixPlugin::make(),
    ]);
}
```

## Configuration

Published config: `config/storix.php`

Key options:

- `storix.customer_model` (default: `App\\Models\\Accounts\\Account`)
- `storix.customer_table` (default from env `STORIX_CUSTOMER_TABLE`, fallback `accounts`)
- `storix.user_model` (default from env `STORIX_USER_MODEL`, fallback `App\\Models\\User`)
- `storix.users_table` (default from env `STORIX_USER_TABLE`, fallback `users`)
- `storix.containers_table` (default: `containers`)
- `storix.dispatches_table` (default: `dispatches`)

## Permissions

Storix registers these permissions (guard defaults to `web`):

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

You can seed explicitly:

```bash
php artisan db:seed --class="Storix\\Database\\Seeders\\StorixPermissionSeeder"
```

## Imports And Exports

### Imports

- `ContainerImporter`: creates/updates containers by `serial`.
- `DispatchImporter`: creates dispatch rows.
- `DispatchReturnImporter`: updates return fields on an existing dispatch by `id`.

### Exports

- `ContainerExporter`
- `DispatchExporter`

## Data Model Notes

- Current migrations use numeric primary/foreign keys.
- Soft deletes are enabled for containers and dispatches.
- `dispatches.metadata` uses `jsonb`.
- Table names are config-driven through `Storix\\Support\\TableNames`.

## Testing

```bash
composer test
```
