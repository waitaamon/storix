# Storix

Storix is a Filament v5 plugin for tracking reusable containers from reservation and dispatch through return, damage, loss, or voiding.

It provides:

- Container master data with serial numbers, activation state, replacement valuation, metadata, and soft deletes.
- Draft, approved, and voided dispatch lifecycles with declared quantity controls and audit fields.
- Per-container dispatch entries and return-condition tracking.
- Transactional actions for creation, reservation, approval, receipt, and voiding.
- Filament resources, bulk imports and exports, filters, relation managers, and fleet metrics.
- Spatie Permission policies and automatic permission registration.
- After-commit lifecycle events for host ERP and accounting integrations.

## Requirements

- PHP 8.5+
- Laravel 13+
- Livewire 4+
- Filament 5+
- PostgreSQL for the production schema (`jsonb` columns are used by the package migrations)
- A host user model and user table with numeric primary keys
- A host delivery-note model and table with numeric primary keys

Storix also installs Spatie Laravel Permission, Spatie Model States, and Spatie Laravel Package Tools through Composer.

## Installation

Install the package and publish its configuration:

```bash
composer require waitaamon/storix
php artisan vendor:publish --tag=storix-config
```

Review `config/storix.php` before running migrations. Table names are read while the migrations execute, so table-name overrides must be configured first.

Storix uses Filament's queued import and export actions. If the host application has not already installed their supporting tables, follow the [Filament import](https://filamentphp.com/docs/5.x/actions/import) and [export](https://filamentphp.com/docs/5.x/actions/export) setup:

```bash
php artisan make:queue-batches-table
php artisan make:notifications-table
php artisan vendor:publish --tag=filament-actions-migrations
```

Run all application and package migrations, then register Storix permissions deterministically:

```bash
php artisan migrate
php artisan db:seed --class="Storix\\Database\\Seeders\\StorixPermissionSeeder"
```

The permission seeder is optional when the standard `permissions` table already exists before the Storix service provider boots, because permissions are also registered automatically.

## Registering The Filament Plugin

Add the plugin to each Filament panel that should expose Storix:

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

The plugin registers the container, dispatch, and dispatch-entry resources under a `Storix` navigation group.

## Configuration

The published file is `config/storix.php`. Its configuration values are compatible with Laravel's `config:cache` command.

### Models

| Configuration key | Environment variable | Default |
| --- | --- | --- |
| `storix.models.container` | `STORIX_CONTAINER_MODEL` | `Storix\Models\Container` |
| `storix.models.dispatch` | `STORIX_DISPATCH_MODEL` | `Storix\Models\Dispatch` |
| `storix.models.dispatch_entry` | `STORIX_DISPATCH_ENTRY_MODEL` | `Storix\Models\DispatchEntry` |
| `storix.models.delivery_note` | `STORIX_DELIVERY_NOTE_CLASS` | `App\Models\Sales\DeliveryNote` |
| `storix.models.user` | `STORIX_USER_MODEL` | `App\Models\User` |

The model overrides are used by resources, relationships, policy registration, and morph aliases. The lifecycle actions currently operate on Storix's concrete `Container`, `Dispatch`, and `DispatchEntry` models, so replacing those three models is an advanced integration and the replacements must remain compatible with the built-in lifecycle services.

### Tables

| Configuration key | Environment variable | Default |
| --- | --- | --- |
| `storix.tables.containers` | `STORIX_CONTAINERS_TABLE` | `storix_containers` |
| `storix.tables.dispatches` | `STORIX_DISPATCHES_TABLE` | `storix_dispatches` |
| `storix.tables.dispatch_entries` | `STORIX_DISPATCH_ENTRIES_TABLE` | `storix_dispatch_entries` |
| `storix.tables.delivery_notes` | `STORIX_DELIVERY_NOTE_TABLE` | `delivery_notes` |
| `storix.tables.users` | `STORIX_USER_TABLE` | `users` |

The configured user and delivery-note tables must exist before the Storix migrations run because dispatches create foreign keys to both tables.

### Labels

| Configuration key | Environment variable | Default |
| --- | --- | --- |
| `storix.labels.container` | `STORIX_CONTAINER_LABEL` | `container` |
| `storix.labels.dispatch` | `STORIX_DISPATCH_LABEL` | `dispatch` |
| `storix.labels.dispatch_entry` | `STORIX_DISPATCH_ENTRY_LABEL` | `dispatch entry` |

These labels customize the corresponding Filament resource labels and selected action text.

### Financial Year Integration

`STORIX_FINANCIAL_YEAR_SERVICE_CLASS` defaults to `App\Services\FinancialYearService`. If the class exists and exposes a static `selectedFinancialYear()` method, Storix uses the returned object's `start_date` and `end_date` values to:

- Limit selectable delivery notes by `transaction_date`.
- Limit dispatch and return date pickers.
- Scope the dispatch table to the selected financial year.

If the configured class or method does not exist, Storix applies no financial-year bounds.

### Delivery Note Query Modifier

`STORIX_DELIVERY_NOTE_QUERY_MODIFIER` defaults to `Storix\Support\DefaultDeliveryNoteQueryModifier`.

The default modifier only exposes delivery notes that:

- Have a `NULL` `dispatched_at` value.
- Have `state = approved`.
- Fall within the selected financial year by `transaction_date`, when a financial year is available.

To customize that query, implement `Storix\Contracts\DeliveryNoteQueryModifier`:

```php
namespace App\Storix;

use Illuminate\Database\Eloquent\Builder;
use Storix\Contracts\DeliveryNoteQueryModifier;

final class ApprovedDeliveryNoteQueryModifier implements DeliveryNoteQueryModifier
{
    public function __invoke(Builder $query): Builder
    {
        return $query->where('state', 'approved');
    }
}
```

Configure the class name rather than a closure so Laravel can cache the configuration:

```dotenv
STORIX_DELIVERY_NOTE_QUERY_MODIFIER="App\\Storix\\ApprovedDeliveryNoteQueryModifier"
```

The modifier is resolved through Laravel's service container, so it may have constructor dependencies.

Storix still accepts the closure-based modifier used by previously published configuration files. Replace that closure with a modifier class before running `php artisan config:cache`, because closures cannot be exported into Laravel's configuration cache.

### Permissions

The remaining permission settings are configured in `config/storix.php`:

```php
'permissions' => [
    'register' => true,
    'guard_name' => 'web',
],
```

Set `register` to `false` if the host application manages permission records itself.

## Host Application Contract

### Delivery Note Model

The configured delivery-note model must be an Eloquent model. The built-in Filament UI expects it to expose:

- A `code` attribute.
- A `customer` relationship whose related model has a `name` attribute.
- The columns required by the configured delivery-note query modifier.

The default modifier expects `state`, `transaction_date`, and `dispatched_at`. On approval, Storix updates the configured delivery-note table's `dispatched_at` column with the approval timestamp. That synchronization is skipped when the table or column does not exist.

### User Model

The configured user model must support Laravel's authorization `can()` method. In a standard Spatie Permission integration, the user model should use `Spatie\Permission\Traits\HasRoles` and its guard must match `storix.permissions.guard_name`.

### Identifier Types

The package migrations use Laravel `id()` and `foreignId()` columns. The host user and delivery-note identifiers must therefore be compatible numeric keys. UUID host keys require customized migrations and compatible model integration.

## Filament Features

### Containers

- List, create, view, and edit pages.
- Name and serial uniqueness validation.
- Active/inactive state and replacement cost/currency fields.
- Soft-delete filtering.
- CSV import and bulk export.
- Dispatch-history relation manager with customer, delivery note, dispatch date, return date, and condition.

### Dispatches

- Creation with delivery note, dispatch date, declared quantity, optional initial containers, and note.
- Generated codes in the form `DSP-{yymmdd}{padded-id}`.
- State-aware editing and relation management for users governed by granular dispatch permissions.
- Container attachment and approval services that always reject non-draft dispatches.
- Approval and void actions with policy authorization and confirmation.
- Filters for customer, dispatch date range, return condition, return date range, and soft-deleted records.
- Export of dispatch-level data.

### Dispatch Entries And Returns

- A consolidated list of dispatched containers.
- Individual and bulk receipt actions.
- Return date, condition, note, and receiving-user capture.
- Bulk return import by container serial.
- Per-entry lifecycle export.

### Fleet Overview

The container page includes one overview widget that refreshes every 60 seconds and reports:

- Total containers and active/inactive counts.
- Containers currently in use on approved dispatches.
- Fleet utilization.
- Return damage rate.
- Average and oldest open-dispatch age.
- Lost-container replacement exposure, kept separate by currency.

## Lifecycle Rules

Dispatch state transitions are:

```text
draft ──► approved
  │           │
  └───────────┴──► voided
```

### Draft Creation And Reservation

- A dispatch quantity must be at least one when saved through the model or lifecycle action.
- Drafts may be created with or without initial containers.
- Containers may only be attached to draft dispatches.
- Only active containers without an open dispatch entry are available for reservation.
- Duplicate container IDs are normalized before attachment.

### Approval

Approval is transactional and requires:

- A draft dispatch.
- At least one active attached container.
- An attached-container count exactly equal to the declared quantity.
- No conflicting open entry for any attached container.

Approval records `approved_by` and `approved_at`, transitions the state, updates the delivery note when supported, emits `DispatchApproved`, and emits one `ContainerDispatched` event per entry.

### Returns

Only entries from approved dispatches can be received. A return date cannot precede the dispatch date, and an entry can only be received once.

Supported conditions are:

| Value | Label | Behavior |
| --- | --- | --- |
| `good` | Returned Good | Emits `ContainerReturned` and releases the container for reuse. |
| `damaged` | Returned Damaged | Emits `ContainerReturned` and `ContainerDamaged`. |
| `lost` | Lost | Emits `ContainerLost` and marks the container inactive. |

### Voiding

- Draft and approved dispatches may transition to voided.
- A reason is required.
- Dispatches with any recorded return activity cannot be voided and require a host reversal workflow.
- Voiding records the actor, timestamp, and reason, then soft-deletes outstanding entries so their containers are released.

All lifecycle services use database transactions and row locks. Open-entry exclusivity is enforced by these services rather than by a database partial unique index, so integrations should use the provided actions and importers instead of writing lifecycle rows directly.

## Programmatic Draft Generation

Host events, jobs, and actions can request draft creation without depending on Filament:

```php
use App\Events\DeliveryNoteApproved;
use Storix\Data\CreateDispatchData;
use Storix\Events\GenerateDraftDispatchRequested;

GenerateDraftDispatchRequested::dispatch(new CreateDispatchData(
    deliveryNoteId: $deliveryNote->getKey(),
    dispatchedBy: $actor->getKey(),
    quantity: count($containerIds),
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

`GenerateDraftDispatchRequested` implements `ShouldDispatchAfterCommit`. Its registered listener synchronously delegates to `CreateDispatchAction` after the surrounding transaction commits, so a rolled-back producer transaction creates no draft.

Retryable producers should supply a stable `idempotencyKey`:

- Repeating an equivalent keyed request returns the existing draft without creating duplicate reservations.
- Reusing the key for a different delivery note, actor, quantity, date, note, container set, or metadata raises a `DomainException`.
- Omitting the key preserves ordinary non-idempotent creation behavior.

Draft generation reserves containers only. Accounting and operational posting remain approval-time responsibilities.

## Imports And Exports

### Imports

| Importer | Required mapping | Behavior |
| --- | --- | --- |
| `ContainerImporter` | `name`, `serial` | Creates or updates by serial; also supports active state, replacement cost, currency, and description. |
| `DispatchEntryImporter` | `serial` | Attaches an available container to the owning draft dispatch through the lifecycle action. |
| `DispatchReturnImporter` | `serial`, `return_date`, `return_condition`, `return_note` | Finds the open entry on an approved dispatch and records its return; the note mapping may be blank. |

Return conditions accepted by imports are `good`, `damaged`, and `lost`.

Filament processes imports in queued jobs. Keep a queue worker running in environments that do not use the synchronous queue driver. Importers do not add per-row policy checks, so their actions should only be exposed to trusted operators with appropriate panel access.

### Exports

- `ContainerExporter` exports container master and valuation data.
- `DispatchExporter` exports dispatch-level customer, delivery note, quantity, audit, note, and state data.
- `DispatchEntryExporter` exports per-container dispatch, valuation, customer, delivery note, and return data.

Use the dispatch-entry export when container serials or return details are required; the dispatch export intentionally remains dispatch-level.

## Permissions And Policies

Storix registers the following permissions for the configured guard:

### Containers

- `manage.containers`
- `viewAny.containers`
- `view.containers`
- `create.containers`
- `update.containers`
- `delete.containers`
- `restore.containers`
- `forceDelete.containers`

### Dispatches

- `manage.dispatches`
- `viewAny.dispatches`
- `view.dispatches`
- `create.dispatches`
- `update.dispatches`
- `delete.dispatches`
- `restore.dispatches`
- `forceDelete.dispatches`
- `approve.dispatches`
- `void.dispatches`

### Dispatch Entries

- `manage.dispatch-entries`
- `viewAny.dispatch-entries`
- `view.dispatch-entries`
- `create.dispatch-entries`
- `update.dispatch-entries`
- `delete.dispatch-entries`
- `restore.dispatch-entries`
- `forceDelete.dispatch-entries`
- `receive.dispatch-entries`

Each `manage.*` permission short-circuits the corresponding policy and grants full policy access. For users relying on granular permissions, dispatch updates, deletes, restores, and force-deletes are limited to drafts; approval is draft-only; voiding is unavailable once already voided; and receipt is limited to entries without a return date. Lifecycle actions repeat their domain checks, so invalid approvals, duplicate receipts, non-draft attachments, and voids with return activity are rejected even when a `manage.*` permission authorizes the UI action.

The service provider also registers policies for the configured models and these morph aliases:

- `storix_container`
- `storix_dispatch`
- `storix_dispatch_entry`

## Events And ERP Integration

Storix does not post general-ledger journals. The host application can consume these after-commit events:

| Event | Emitted when |
| --- | --- |
| `DispatchApproved` | A dispatch transitions to approved. |
| `DispatchVoided` | A dispatch transitions to voided. |
| `ContainerDispatched` | Each attached entry is dispatched during approval. |
| `ContainerReturned` | A container is returned in good or damaged condition. |
| `ContainerDamaged` | A returned container is marked damaged. |
| `ContainerLost` | A container is recorded as lost and deactivated. |

Use event listeners in the host ERP for GL entries, customer recovery, write-offs, repair workflows, tax handling, and additional audit records. If an approved dispatch already has downstream financial documents, perform an explicit host reversal rather than treating Storix's void event as a complete accounting reversal.

## Data Model Notes

- Storix uses numeric primary and foreign keys.
- Containers, dispatches, and dispatch entries use soft deletes.
- Container names and serials are unique.
- Replacement cost is stored as `decimal(19, 4)` with a three-character currency code.
- Dispatch code and idempotency key are unique when present.
- Dispatches store declared quantity separately from attached-entry count.
- `return_date` is a date-only value; audit timestamps retain precise times.
- Metadata columns use PostgreSQL `jsonb`.
- Time-based columns use timezone-aware timestamps.
- Table names are resolved through `Storix\Support\TableNames`.

Application-level lifecycle invariants, including positive quantity and one open entry per container, can be bypassed by direct query-builder inserts. Use the supplied actions for writes that must preserve those invariants.

## Testing And Quality Checks

Run the Pest suite:

```bash
composer test
```

Run static analysis and non-mutating style/refactoring checks:

```bash
composer analyse
./vendor/bin/pint --test
./vendor/bin/rector process --dry-run
./vendor/bin/pest --type-coverage --compact --min=95
```

The aggregate command applies configured Rector transformations before running Pint, PHPStan, Pest, and the 95% type-coverage threshold:

```bash
composer lint
```

The package test harness uses an in-memory SQLite database. Validate the migrations and lifecycle paths against PostgreSQL in the host application's staging environment before production deployment.
