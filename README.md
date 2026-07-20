# Storix

Storix is a Filament v5 plugin for reusable container lifecycle tracking.

## Highlights

- Container management with soft deletes (`name`, `serial`, active state, replacement cost, currency, description, metadata).
- Controlled dispatch and return lifecycle tracking via `dispatches` + `dispatch_entries`.
- Service/action layer for create, attach, approve, receive, and void workflows.
- Filament resources for containers and dispatches, including relation managers for entry-level returns.
- Dashboard widgets for utilization, damage rate, aging, and lost exposure (computed from dispatch entry data).
- Native Filament imports and exports.
- Spatie permission integration with automatic permission registration.
- Pest test suite for CRUD, lifecycle actions, import/export definitions, widgets, and policy checks.

## Requirements

- PHP 8.3+
- Laravel 13+
- Livewire 4+
- Filament 5+

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
- `storix.models.delivery_note` (default from env `STORIX_DELIVERY_NOTE_CLASS`, fallback `App\\Models\\Sales\\DeliveryNote`)
- `storix.models.user` (default from env `STORIX_USER_MODEL`, fallback `App\\Models\\User`)
- `storix.tables.containers` (default from env `STORIX_CONTAINERS_TABLE`, fallback `containers`)
- `storix.tables.dispatches` (default from env `STORIX_DISPATCHES_TABLE`, fallback `dispatches`)
- `storix.tables.dispatch_entries` (default from env `STORIX_DISPATCH_ENTRIES_TABLE`, fallback `dispatch_entries`)
- `storix.tables.delivery_notes` (default from env `STORIX_DELIVERY_NOTE_TABLE`, fallback `delivery_notes`)
- `storix.tables.users` (default from env `STORIX_USER_TABLE`, fallback `users`)
- `storix.financial_year_service_class` (optional host financial-year selector)
- `storix.delivery_note_query_modifier` (host-specific delivery note selection query)
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
- `approve.dispatches`
- `void.dispatches`
- `viewAny.dispatch-entries`
- `view.dispatch-entries`
- `create.dispatch-entries`
- `update.dispatch-entries`
- `delete.dispatch-entries`
- `restore.dispatch-entries`
- `forceDelete.dispatch-entries`
- `receive.dispatch-entries`

Permissions are auto-registered by `StorixServiceProvider` when `storix.permissions.register` is `true` (default), so manual seeding is optional.

You can seed explicitly:

```bash
php artisan db:seed --class="Storix\\Database\\Seeders\\StorixPermissionSeeder"
```

## Imports And Exports

### Imports

- `ContainerImporter`: creates/updates containers by `serial`, including replacement valuation fields.
- `DispatchEntryImporter`: attaches available containers to a draft dispatch by `serial`.
- `DispatchReturnImporter`: receives currently dispatched containers by `serial`.

### Exports

- `ContainerExporter`
- `DispatchExporter`
- `DispatchEntryExporter`

## Data Model Notes

- Migrations use numeric primary/foreign keys.
- Soft deletes are enabled for containers, dispatches, and dispatch entries.
- Dispatches support `draft`, `approved`, and `voided` states.
- Draft dispatch entries reserve containers. Voiding a dispatch soft-deletes its unreturned entries and releases those reservations.
- Approval records `approved_by` and `approved_at`; voiding records `voided_by`, `voided_at`, and `void_reason`.
- A unique partial index prevents one container from having more than one open dispatch entry.
- `dispatches.metadata` uses `jsonb`.
- Container loss exposure is based on typed `replacement_cost` and `replacement_currency` fields, not JSON metadata.
- Table names are config-driven through `Storix\\Support\\TableNames`.

## Event-Driven Draft Generation

Host application events, jobs, and actions can request a draft dispatch without depending on Filament or duplicating lifecycle logic:

```php
use App\Events\DeliveryNoteApproved;
use Storix\Data\CreateDispatchData;
use Storix\Events\GenerateDraftDispatchRequested;

GenerateDraftDispatchRequested::dispatch(new CreateDispatchData(
    deliveryNoteId: $deliveryNote->getKey(),
    dispatchedBy: $actor->getKey(),
    dispatchedAt: now(),
    dispatchNote: 'Generated after delivery note approval',
    containerIds: $containerIds,
    idempotencyKey: sprintf('delivery-note:%s:draft-dispatch', $deliveryNote->getKey()),
    metadata: [
        'source' => DeliveryNoteApproved::class,
        'correlation_id' => $correlationId,
    ],
));
```

The event is dispatched after the surrounding database transaction commits. Its listener synchronously delegates to `CreateDispatchAction`, so validation failures remain visible to the caller. Retryable producers should supply a stable `idempotencyKey`: repeating the same request is a no-op, while reusing the key for a different payload raises a `DomainException`. The key is optional so existing direct and Filament creation workflows remain compatible.

Draft generation only reserves containers. It does not post journals or trigger dispatch accounting; approval remains the operational and accounting integration boundary.

## Accounting And ERP Controls

Storix does not post general ledger journals by itself. It emits lifecycle events that a host ERP can consume for accounting integration:

Successful dispatch approval also mirrors the approval timestamp to the `dispatched_at` column of the delivery note identified by `delivery_note_id`, using the table configured at `storix.tables.delivery_notes`. This update participates in the approval transaction. For compatibility with host applications that do not expose the table or column, synchronization is skipped when either is absent and approval continues normally.

- `DispatchApproved`
- `DispatchVoided`
- `ContainerDispatched`
- `ContainerReturned`
- `ContainerDamaged`
- `ContainerLost`

Use these events to create host-specific GL, AR recovery, write-off, repair, tax, and audit workflows. Approved dispatches should be reversed through explicit host accounting workflows when financial documents already exist; Storix only permits simple voiding while there is no return activity.

## Testing

```bash
composer lint
```

This runs Rector, the Pint style check, PHPStan analysis, the complete Pest suite, and a 95% minimum type-coverage check.
