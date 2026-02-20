# Storix

Storix is a Filament v4 plugin for reusable container lifecycle tracking.

## Highlights

- Container management with soft deletes (`name`, `serial`, active state, description, metadata).
- Dispatch and return lifecycle tracking via `dispatches` + `dispatch_entries`.
- Filament resources for containers and dispatches, including relation managers for entry-level returns.
- Dashboard widgets for utilization, damage rate, aging, and lost exposure (computed from dispatch entry data).
- Native Filament imports and exports.
- Spatie permission integration with automatic permission registration.
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

- `storix.models.container` (default from env `STORIX_CONTAINER_MODEL`, fallback `Storix\\Models\\Container`)
- `storix.models.dispatch` (default from env `STORIX_DISPATCH_MODEL`, fallback `Storix\\Models\\Dispatch`)
- `storix.models.dispatch_entry` (default from env `STORIX_DISPATCH_ENTRY_MODEL`, fallback `Storix\\Models\\DispatchEntry`)
- `storix.models.user` (default from env `STORIX_USER_MODEL`, fallback `App\\Models\\User`)
- `storix.tables.containers` (default from env `STORIX_CONTAINERS_TABLE`, fallback `containers`)
- `storix.tables.dispatches` (default from env `STORIX_DISPATCHES_TABLE`, fallback `dispatches`)
- `storix.tables.dispatch_entries` (default from env `STORIX_DISPATCH_ENTRIES_TABLE`, fallback `dispatch_entries`)
- `storix.tables.users` (default from env `STORIX_USER_TABLE`, fallback `users`)
- `storix.permissions.register` (default: `true`)
- `storix.permissions.guard_name` (default: `web`)

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
- `viewAny.dispatch-entries`
- `view.dispatch-entries`
- `create.dispatch-entries`
- `update.dispatch-entries`
- `delete.dispatch-entries`
- `restore.dispatch-entries`
- `forceDelete.dispatch-entries`

Permissions are auto-registered by `StorixServiceProvider` when `storix.permissions.register` is `true` (default), so manual seeding is optional.

You can seed explicitly:

```bash
php artisan db:seed --class="Storix\\Database\\Seeders\\StorixPermissionSeeder"
```

## Imports And Exports

### Imports

- `ContainerImporter`: creates/updates containers by `serial`.
- `DispatchEntryImporter`: creates dispatch entries rows.
- `DispatchReturnImporter`: updates return fields on an existing dispatch entry by `id`.

### Exports

- `ContainerExporter`
- `DispatchExporter`
- `DispatchEntryExporter`

## Data Model Notes

- Current migrations use numeric primary/foreign keys.
- Soft deletes are enabled for containers, dispatches, and dispatch entries.
- `dispatches.metadata` uses `jsonb`.
- Table names are config-driven through `Storix\\Support\\TableNames`.

## Testing

```bash
composer test
```
