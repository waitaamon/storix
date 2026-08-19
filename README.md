# Storix

Storix is a Laravel 13 and Filament 5 package for controlling reusable-container dispatch, physical custody, return, damage, and loss.

It provides:

- Container master data with unique serials, activation state, replacement valuation, metadata, and soft deletes.
- Customer-owned dispatch documents with optional delivery-note references.
- Approval-posted container-return documents with independent maker-checker authorization.
- Exact reconciliation of every returned serial to its outstanding dispatch entry.
- Cross-customer return controls and customer quantity-balance reporting.
- Transactional actions, row locking, idempotent draft generation, policies, permissions, events, factories, and tests.
- Filament resources, relation managers, filters, import/export actions, and fleet metrics.

Storix is a quantity and physical-custody subledger. It does not create IFRS general-ledger journals, tax entries, receivables, provisions, or write-offs. Host ERP listeners can consume Storix events to perform those accounting operations under the host application's chart of accounts and approval controls.

## Requirements

- PHP 8.5+
- Laravel 13+
- Livewire 4+
- Filament 5+
- PostgreSQL in production because the base migrations use `jsonb`
- Host customer, user, and optional delivery-note models with numeric keys compatible with Laravel `foreignId`; the default customer query also expects `category.slug`

The package also uses Spatie Laravel Permission, Spatie Model States, and Spatie Laravel Package Tools.

## Installation

Install the package and publish its configuration:

```bash
composer require waitaamon/storix
php artisan vendor:publish --tag=storix-config
```

Review `config/storix.php` before running migrations. Model and table overrides are read while migrations execute and must be configured first.

Filament's queued import and export actions require their supporting tables:

```bash
php artisan make:queue-batches-table
php artisan make:notifications-table
php artisan vendor:publish --tag=filament-actions-migrations
```

Run the migrations and synchronize permissions:

```bash
php artisan migrate
php artisan storix:sync-permissions
```

Run `storix:sync-permissions` after package upgrades that add permissions. It creates missing permissions without changing existing role or user assignments.

Register the plugin in every required Filament panel:

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

The plugin registers containers, dispatches, dispatch entries, container returns, container-return entries, and the customer container balance report in the `Storix` navigation group.

## Configuration

The published configuration is compatible with `php artisan config:cache`.

### Models

| Key | Environment variable | Default |
| --- | --- | --- |
| `storix.models.container` | `STORIX_CONTAINER_MODEL` | `Storix\Models\Container` |
| `storix.models.dispatch` | `STORIX_DISPATCH_MODEL` | `Storix\Models\Dispatch` |
| `storix.models.dispatch_entry` | `STORIX_DISPATCH_ENTRY_MODEL` | `Storix\Models\DispatchEntry` |
| `storix.models.container_return` | `STORIX_CONTAINER_RETURN_MODEL` | `Storix\Models\ContainerReturn` |
| `storix.models.container_return_entry` | `STORIX_CONTAINER_RETURN_ENTRY_MODEL` | `Storix\Models\ContainerReturnEntry` |
| `storix.models.container_movement` | `STORIX_CONTAINER_MOVEMENT_MODEL` | `Storix\Models\ContainerMovement` |
| `storix.models.customer` | `STORIX_CUSTOMER_MODEL` | `App\Models\Accounts\Account` |
| `storix.models.delivery_note` | `STORIX_DELIVERY_NOTE_CLASS` | `App\Models\Sales\DeliveryNote` |
| `storix.models.user` | `STORIX_USER_MODEL` | `App\Models\User` |

Overrides are used by relationships, resources, policy registration, and morph aliases. Replacing a built-in Storix model is an advanced integration: the replacement must preserve the fields, casts, relationships, state configuration, and lifecycle behavior expected by the package actions.

### Tables

| Key | Environment variable | Default |
| --- | --- | --- |
| `storix.tables.containers` | `STORIX_CONTAINERS_TABLE` | `storix_containers` |
| `storix.tables.dispatches` | `STORIX_DISPATCHES_TABLE` | `storix_dispatches` |
| `storix.tables.dispatch_entries` | `STORIX_DISPATCH_ENTRIES_TABLE` | `storix_dispatch_entries` |
| `storix.tables.container_returns` | `STORIX_CONTAINER_RETURNS_TABLE` | `storix_container_returns` |
| `storix.tables.container_return_entries` | `STORIX_CONTAINER_RETURN_ENTRIES_TABLE` | `storix_container_return_entries` |
| `storix.tables.container_movements` | `STORIX_CONTAINER_MOVEMENTS_VIEW` | `storix_container_movements` |
| `storix.tables.customers` | `STORIX_CUSTOMER_TABLE` | `customers` |
| `storix.tables.delivery_notes` | `STORIX_DELIVERY_NOTE_TABLE` | `delivery_notes` |
| `storix.tables.users` | `STORIX_USER_TABLE` | `users` |

The customer and user tables must exist before Storix migrations run. The delivery-note table must also exist for upgrades containing legacy dispatches that reference it.

### Labels

| Key | Environment variable | Default |
| --- | --- | --- |
| `storix.labels.container` | `STORIX_CONTAINER_LABEL` | `container` |
| `storix.labels.dispatch` | `STORIX_DISPATCH_LABEL` | `dispatch` |
| `storix.labels.dispatch_entry` | `STORIX_DISPATCH_ENTRY_LABEL` | `dispatch entry` |
| `storix.labels.container_return` | `STORIX_CONTAINER_RETURN_LABEL` | `container return` |
| `storix.labels.container_return_entry` | `STORIX_CONTAINER_RETURN_ENTRY_LABEL` | `container return entry` |
| `storix.labels.container_movement` | `STORIX_CONTAINER_MOVEMENT_LABEL` | `container movement` |

`storix_container_movements` is a live, non-materialized database view. Configure its name before migrations run, just like the package tables; it does not require a refresh job.

### Navigation

| Key | Environment variable | Default |
| --- | --- | --- |
| `storix.navigation.customer_container_balances_label` | `STORIX_CUSTOMER_CONTAINER_BALANCES_NAVIGATION_LABEL` | `Customer Container Balances` |

The label controls the customer container balance report's item in the existing `Storix` navigation group.

### Financial year

`STORIX_FINANCIAL_YEAR_SERVICE_CLASS` defaults to `App\Services\FinancialYearService`. If the class exposes `selectedFinancialYear()`, the returned `start_date` and `end_date` bound:

- Delivery-note selection by `transaction_date`.
- Dispatch and return date pickers.
- The dispatch list.

No bounds are applied when the configured integration is unavailable.

### Customer query

`STORIX_CUSTOMER_QUERY_MODIFIER` defaults to `Storix\Support\DefaultCustomerQueryModifier`. The default limits the dispatch customer selector to accounts receivable using:

```php
$query->whereRelation('category', 'slug', 'accounts-receivable');
```

The configured customer model must therefore expose a `category` relationship whose related model has a `slug` attribute, unless the modifier is replaced. Custom integrations implement `Storix\Contracts\CustomerQueryModifier`:

```php
namespace App\Storix;

use Illuminate\Database\Eloquent\Builder;
use Storix\Contracts\CustomerQueryModifier;

final class ActiveReceivableCustomerQueryModifier implements CustomerQueryModifier
{
    public function __invoke(Builder $query): Builder
    {
        return $query
            ->whereRelation('category', 'slug', 'accounts-receivable')
            ->where('is_active', true);
    }
}
```

```dotenv
STORIX_CUSTOMER_QUERY_MODIFIER="App\\Storix\\ActiveReceivableCustomerQueryModifier"
```

The modifier is applied to Filament's customer relationship query, including preloaded options, searching, selected-record validation, and the customer container balance report. A custom modifier replaces the default rather than extending it, so include every required customer constraint in the implementation.

### Delivery-note query

`STORIX_DELIVERY_NOTE_QUERY_MODIFIER` defaults to `Storix\Support\DefaultDeliveryNoteQueryModifier`. The default exposes delivery notes with:

- `dispatched_at IS NULL`
- `state = approved`
- A `transaction_date` in the selected financial year, when applicable

Custom integrations implement `Storix\Contracts\DeliveryNoteQueryModifier`:

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

```dotenv
STORIX_DELIVERY_NOTE_QUERY_MODIFIER="App\\Storix\\ApprovedDeliveryNoteQueryModifier"
```

Use a class name instead of a closure so the configuration remains cacheable.

### Permission guard

```php
'permissions' => [
    'guard_name' => 'web',
],
```

### Cross-return reconciliation

Storix registers its historical cross-return reconciliation command and daily schedule from the package service provider; the host application does not need to add anything to `routes/console.php`.

```bash
php artisan storix:reconcile-cross-returns
php artisan storix:reconcile-cross-returns --dry-run
```

Every run writes a unique Laravel-standard single-channel log under `storage/logs/storix/cross-return-reconciliation/` by default. The report is file-only and contains structured context for the run configuration, every evaluated approved cross return, controlled discrepancies, exceptions, and completion totals. These `.log` files are discoverable by Opcodes Laravel Log Viewer when its default recursive `**/*.log` include pattern is enabled. A dry run performs the same analysis without database corrections.

| Key | Environment variable | Default |
| --- | --- | --- |
| `storix.cross_return_reconciliation.report_directory` | `STORIX_CROSS_RETURN_RECONCILIATION_REPORT_DIRECTORY` | `storage/logs/storix/cross-return-reconciliation` |
| `storix.cross_return_reconciliation.chunk_size` | `STORIX_CROSS_RETURN_RECONCILIATION_CHUNK_SIZE` | `500` |
| `storix.cross_return_reconciliation.deadlock_retries` | `STORIX_CROSS_RETURN_RECONCILIATION_DEADLOCK_RETRIES` | `3` |
| `storix.cross_return_reconciliation.schedule.enabled` | `STORIX_CROSS_RETURN_RECONCILIATION_SCHEDULE_ENABLED` | `true` |
| `storix.cross_return_reconciliation.schedule.timezone` | `STORIX_CROSS_RETURN_RECONCILIATION_SCHEDULE_TIMEZONE` | `Africa/Nairobi` |

The automatic event runs daily at exactly midnight, uses a 120-minute overlap lock, and uses Laravel's single-server scheduling control. In multi-server deployments, `onOneServer()` requires every scheduler node to use the same lock-capable `database`, `memcached`, `dynamodb`, or `redis` cache store.

## Host Application Contract

### Customer

The configured customer model must:

- Extend Eloquent `Model`.
- Use a numeric primary key compatible with `foreignId`.
- Expose a `name` attribute for Filament labels, searches, filters, and exports.
- Satisfy the configured `CustomerQueryModifier`. The default expects `category.slug = accounts-receivable`.

The default `App\Models\Accounts\Account` integration treats accounts-receivable accounts as customers. Applications with a different customer model or category structure should replace both `STORIX_CUSTOMER_MODEL` and `STORIX_CUSTOMER_QUERY_MODIFIER`. Customer container balances are exposed through `CustomerContainerBalanceQuery`; no balance relationship or accessor is required on the host model.

### User

The user model must support Laravel's `can()` authorization method. A normal Spatie integration uses `HasRoles`, with a guard matching `storix.permissions.guard_name`.

The package stores the preparer, approver, dispatcher, voiding user, and audit timestamps as separate control evidence.

### Delivery note

Delivery notes are optional for new dispatches. When used, the configured model must:

- Extend Eloquent `Model`.
- Use a numeric key.
- Expose `code`.
- Expose a `customer` relationship whose related model has `name`.
- Provide the columns required by the configured query modifier.

The selected delivery note must belong to the dispatch customer. Approval updates its configured table's `dispatched_at` value when the table and column exist. Dispatch approval skips delivery-note synchronization when no delivery note was selected.

## Data Model

### Dispatch

A dispatch has a required customer and an optional delivery note:

```text
Dispatch
├── customer_id          required
├── delivery_note_id     nullable
├── dispatched_by
├── quantity
├── dispatched_at
├── state
└── DispatchEntry[]      one serial per entry
```

Codes use `DSP-{yymmdd}{padded-id}`. States are:

```text
draft ──► approved
  │           │
  └───────────┴──► voided
```

Approval is the posting point for outbound custody. It requires:

- A draft dispatch.
- At least one active container.
- Attached serial count equal to declared quantity.
- No conflicting draft reservation or outstanding approved dispatch for any serial.

Approval records the approver and timestamp, optionally synchronizes the delivery note, and emits the dispatch events after commit.

### Container return

```text
ContainerReturn
├── code
├── customer_id
├── user_id              preparer
├── approved_by
├── approved_at
├── transaction_date     date only
├── note
├── state
└── ContainerReturnEntry[]
    ├── container_id
    ├── dispatch_entry_id
    ├── return_condition
    ├── cross_return
    └── note
```

Codes use `CRN-{yymmdd}{padded-id}`. States are:

```text
draft ──► submitted ──► approved
  ▲           │
  └───────────┘
```

- Draft documents and entries are editable.
- Submitted documents are immutable pending approval or return to draft.
- Approval requires a different user from the preparer.
- Approval requires at least one entry.
- Approved documents and entries are immutable and cannot be deleted, restored, reversed, or returned to draft.
- A return date cannot precede the reconciled source dispatch.
- Each approved entry reconciles to exactly one outstanding approved dispatch entry.
- `dispatch_entry_id` is unique, preventing the same custody cycle from being posted twice.
- A serial cannot appear twice on the same return document.
- `cross_return` is derived during approval and is never operator-entered.

Approval is the sole posting point. Draft and submitted returns do not release custody, change balances, affect fleet metrics, or emit physical return/damage/loss events.

Database control indexes support customer/state/date reporting and return/container lookups. A unique index on `container_return_entries.dispatch_entry_id` provides a final database-level guard against reconciling the same dispatch entry twice.

### Return conditions

| Value | Behavior on approval |
| --- | --- |
| `good` | Closes custody, makes the active serial available, and emits `ContainerReturned`. |
| `damaged` | Closes custody and emits `ContainerReturned` plus `ContainerDamaged`. |
| `lost` | Closes custody, marks the serial inactive, and emits `ContainerLost` without `ContainerReturned`. |

### Container movements

`ContainerMovement` is a read-only event projection backed by the live `storix_container_movements` database view. It emits one row for every approved dispatch entry and one row for every approved return entry:

```text
approved dispatch entry ──► dispatch event
approved return entry   ──► return event
```

Each event exposes its physical date, customer, document type, document code, and a nullable cross-return flag. Dispatch rows use the dispatch customer and leave `cross_return` null; return rows use the returning customer and expose the entry's derived cross-return value. Stable keys are namespaced as `dispatch:{entry-id}` and `return:{entry-id}`.

Return events are selected by their own `container_id`; they do not depend on `dispatch_entry_id`. This keeps approved legacy returns visible even when historical reconciliation links are absent. Draft dispatches, voided or deleted outbound entries, submitted returns, and deleted return documents are excluded. Redispatching a returned serial simply appends another dispatch event. The view is live and requires no refresh process. Its model, table, and singular label remain configurable through `storix.models.container_movement`, `storix.tables.container_movements`, and `storix.labels.container_movement`.

### Cross returns

A customer may return a physical serial originally dispatched to another customer.

When customer B returns a serial whose source dispatch belongs to customer A:

- The serial's exact dispatch entry is reconciled, so physical custody is closed.
- The return belongs to customer B and credits B's quantity subledger.
- `cross_return = true` preserves the customer mismatch for exception reporting and follow-up.
- Customer A's dispatch quantity remains attributed to A; it is not silently reclassified.

Cross returns can therefore produce a positive outstanding quantity for A and a negative outstanding quantity for B. This is intentional control information, not a GL balance. The host ERP should investigate or settle inter-customer responsibility under its commercial and accounting policies.

## Customer Quantity Balances

Use the query service without changing the host customer model:

```php
use Storix\Support\CustomerContainerBalanceQuery;

$balance = app(CustomerContainerBalanceQuery::class)
    ->forCustomer($customer->getKey());

$balance->dispatched;
$balance->returned;
$balance->lost;
$balance->outstanding;
```

Only approved dispatches and approved returns are included:

```text
outstanding = dispatched - returned - lost
```

This measures customer-attributed quantities. Physical serial custody is separately determined by exact dispatch-entry reconciliation. The distinction is essential for cross-return controls.

The cumulative customer container balance report is available at `customer-container-balances`. It includes customers that have approved dispatch or return history, including settled zero-balance customers, and omits customers with no qualifying activity. Draft, submitted, voided, soft-deleted, and deleted-entry activity is excluded.

The report presents `Customer`, `Dispatched`, `Returned`, `Lost`, and `Balance`, where:

```text
Returned = good returns + damaged returns
Balance  = dispatched - returned - lost
```

A cross-return remains attributed to the return document's customer. This can intentionally produce positive and negative customer balances while preserving the exact-serial custody reconciliation.

## Filament Features

### Containers

- List, create, view, and edit pages.
- Active state and replacement cost/currency.
- Soft-delete filtering.
- CSV import and bulk export.
- Dispatch-history relation manager.
- Read-only return history showing document, returning customer, date, condition, state, source dispatch, and cross-return flag.
- Read-only chronological movement history showing approved dispatch and return events with date, customer, document, document code, and return-only cross-return control.

### Dispatches

- Required customer selector constrained by `CustomerQuery` (accounts receivable by default).
- Optional delivery-note selector filtered to the selected customer.
- Optional initial serial selection.
- Approval and void actions.
- Direct customer columns, filters, infolists, and exports.
- Draft entry relation manager and dispatch movement export.

### Dispatch entries

- Read-only outbound movement list with customer, delivery-note, serial, dispatch date, and dispatcher context.
- Customer and dispatch-date filters plus dedicated export.
- Clicking a row opens its parent dispatch; there is no standalone dispatch-entry view page.

### Container returns

- List, create, view, and edit pages.
- Customer, transaction date, state, preparer, approver, note, and entry counts.
- Customer, state, and transaction-date filters.
- Submit, return-to-draft, and approval actions governed by policies and transactional domain actions.
- Draft entries relation manager with controlled CSV bulk import.
- Dedicated document export.

### Container-return entries

- List, create, and edit pages.
- Container, condition, return document, source dispatch, and cross-return details.
- Customer, state, date, condition, and cross-return filters.
- Dedicated reconciliation-entry export.
- Clicking a row, or completing standalone create/edit, opens the parent container return; entries do not have a standalone view page or infolist.

### Fleet overview

The container resource includes a widget reporting:

- Total and active/inactive containers.
- Serial quantities in current approved custody.
- Fleet utilization.
- Approved-return damage rate.
- Average and oldest outstanding-custody age.
- Lost replacement exposure separated by currency.

### Customer container balances

- Cumulative, activity-only customer quantity-control report.
- Searchable and sortable customer and aggregate quantity columns.
- Approved activity only, with good and damaged returns combined and lost containers shown separately.
- Paginated row selection and bulk CSV/XLSX export using the same filtered aggregate query.
- Spreadsheet-formula protection for exported customer names.
- Navigation, direct page access, and export require `viewAny.customer-container-balances`.

## Programmatic Actions

### Customer dispatch without a delivery note

```php
use Storix\Actions\CreateDispatchAction;
use Storix\Data\CreateDispatchData;

$dispatch = app(CreateDispatchAction::class)->handle(new CreateDispatchData(
    deliveryNoteId: null,
    dispatchedBy: $actor->getKey(),
    quantity: count($containerIds),
    customerId: $customer->getKey(),
    dispatchedAt: now(),
    dispatchNote: 'Direct customer dispatch',
    containerIds: $containerIds,
    idempotencyKey: "customer:{$customer->getKey()}:dispatch:{$externalId}",
    metadata: ['external_id' => $externalId],
));
```

When a delivery note is supplied, it must belong to `customerId`. The customer and nullable delivery note participate in the idempotency fingerprint.

### Event-requested draft generation

```php
use Storix\Data\CreateDispatchData;
use Storix\Events\GenerateDraftDispatchRequested;

GenerateDraftDispatchRequested::dispatch(new CreateDispatchData(
    deliveryNoteId: $deliveryNote?->getKey(),
    dispatchedBy: $actor->getKey(),
    quantity: count($containerIds),
    customerId: $customer->getKey(),
    dispatchedAt: now(),
    containerIds: $containerIds,
    idempotencyKey: "source-document:{$sourceDocument->getKey()}:storix-dispatch",
));
```

The request event dispatches after commit. Repeating an equivalent keyed request returns the existing draft; reusing the key with a different fingerprint throws `DomainException`.

### Return lifecycle

The public return write services are:

- `CreateContainerReturnAction`
- `UpdateContainerReturnAction`
- `DeleteContainerReturnAction`
- `AddContainerReturnEntryAction`
- `AddContainerReturnEntryBySerialAction`
- `UpdateContainerReturnEntryAction`
- `DeleteContainerReturnEntryAction`
- `SubmitContainerReturnAction`
- `ReturnContainerReturnToDraftAction`
- `ApproveContainerReturnAction`

All enforce state rules inside transactions. Approval locks the document, serials, and source entries before reconciling custody.

For integrations that receive a scanned or imported serial rather than a container ID, use the serial-based entry action:

```php
use Storix\Actions\AddContainerReturnEntryBySerialAction;
use Storix\Data\AddContainerReturnEntryBySerialData;
use Storix\Enums\ReturnCondition;

$entry = app(AddContainerReturnEntryBySerialAction::class)->handle(
    $containerReturn,
    new AddContainerReturnEntryBySerialData(
        serial: $serial,
        condition: ReturnCondition::Good,
        note: 'Seal inspected on receipt.',
    ),
);
```

The action trims the serial, locks the draft return and container, rejects inactive or duplicate containers, and requires an outstanding approved dispatch. It creates a draft entry only; source reconciliation and `cross_return` derivation remain approval-time responsibilities.

## Imports And Exports

### Imports

| Importer | Required mapping | Behavior |
| --- | --- | --- |
| `ContainerImporter` | `name`, `serial` | Creates or updates container master data by serial. |
| `DispatchEntryImporter` | `serial` | Attaches an available serial to the owning draft dispatch through the domain action. |
| `ContainerReturnEntryImporter` | `serial` | Adds an active, outstanding serial to the selected draft return. Optional columns are `return_condition` (`good`, `damaged`, or `lost`) and `note`. |

Return headers remain controlled documents and are not bulk-created by an importer. Entry imports are available only from a draft return's entries relation manager and run through the same transactional domain guard as manual entry creation. Each queued row rechecks the target document state and authorization, rejects duplicate or unavailable serials, and reports failures without posting the return.

Importing entries does not submit or approve the return, reconcile dispatch custody, update approved customer quantity balances, derive cross-return status, or emit return-posting events. Those effects occur only through the normal submit and independent approval workflow, preserving maker-checker control and exact serial reconciliation.

### Exports

- `ContainerExporter`: master and valuation data.
- `DispatchExporter`: dispatch headers and direct customer data.
- `DispatchEntryExporter`: outbound serial movements only.
- `ContainerReturnExporter`: return document headers and audit data.
- `ContainerReturnEntryExporter`: return serials, conditions, source dispatches, and cross-return controls.
- `ContainerMovementExporter`: owner-scoped dispatch and return events with container identity, date, customer, document type, document code, and return-only cross-return control.
- `CustomerContainerBalanceExporter`: selected rows from the filtered customer quantity-balance report.

Export queries eager-load their relationships. Text beginning with spreadsheet formula-control characters is prefixed safely before export.

Filament's published export, notification, job-batch, and queue prerequisites remain host-application responsibilities. The customer balance export supports CSV and XLSX and may be processed by the configured queue worker.

## Permissions And Policies

Run `php artisan storix:sync-permissions` to create the configured-guard permissions.

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

### Dispatch entries

- `manage.dispatch-entries`
- `viewAny.dispatch-entries`
- `view.dispatch-entries`
- `create.dispatch-entries`
- `update.dispatch-entries`
- `delete.dispatch-entries`
- `restore.dispatch-entries`
- `forceDelete.dispatch-entries`

Dispatch entries are outbound movement records. The removed `receive.dispatch-entries` permission is no longer synchronized.

### Container returns

- `manage.container-returns`
- `viewAny.container-returns`
- `view.container-returns`
- `create.container-returns`
- `update.container-returns`
- `delete.container-returns`
- `restore.container-returns`
- `forceDelete.container-returns`
- `submit.container-returns`
- `approve.container-returns`
- `draft.container-returns`

### Container-return entries

- `manage.container-return-entries`
- `viewAny.container-return-entries`
- `view.container-return-entries`
- `create.container-return-entries`
- `update.container-return-entries`
- `delete.container-return-entries`

### Reports

- `viewAny.customer-container-balances`

This permission controls customer balance report navigation, direct access, and bulk export. Run `php artisan storix:sync-permissions` after upgrading so the permission is created for the configured guard.

`manage.*` grants the corresponding permission family, but it does not bypass domain state immutability or maker-checker validation. Policies restrict draft editing and submitted lifecycle actions; transactional services repeat every critical rule.

Registered morph aliases are:

- `storix_container`
- `storix_dispatch`
- `storix_dispatch_entry`
- `storix_container_return`
- `storix_container_return_entry`

## Events And ERP Accounting

Lifecycle events implement `ShouldDispatchAfterCommit`.

| Event | Payload | Emitted when |
| --- | --- | --- |
| `DispatchApproved` | `Dispatch` | Outbound custody is approved. |
| `DispatchVoided` | `Dispatch` | A dispatch is voided. |
| `ContainerDispatched` | `DispatchEntry` | Each serial posts on dispatch approval. |
| `ContainerReturnSubmitted` | `ContainerReturn` | A return is submitted for independent approval. |
| `ContainerReturnApproved` | `ContainerReturn` | A return document posts. |
| `ContainerReturned` | `ContainerReturnEntry` | A good or damaged serial closes custody. |
| `ContainerDamaged` | `ContainerReturnEntry` | An approved returned serial is damaged. |
| `ContainerLost` | `ContainerReturnEntry` | An approved lost serial closes custody and becomes inactive. |

Host ERP listeners can use these events for:

- Container deposits, customer recoveries, and receivable adjustments.
- Repair costs and impairment assessment.
- Lost-container write-offs or customer claims.
- Applicable tax treatment.
- GL journals, reversals, and audit records.

Storix events are operational evidence, not complete accounting entries. Determine recognition, measurement, account mapping, tax, foreign-currency treatment, and approval in the host accounting domain.

## Upgrade From Dispatch-Entry Returns

The container-return upgrade is intentionally data-preserving and should run in a maintenance window with a verified backup.

The migration:

1. Adds nullable `dispatches.customer_id` and makes `delivery_note_id` nullable.
2. Backfills each legacy dispatch customer from its delivery note.
3. Aborts with a clear exception if any legacy dispatch cannot resolve a customer.
4. Groups returned legacy entries by customer and calendar date, creating one approved return document for each customer/date pair.
5. Creates one return entry per legacy dispatch entry, so a grouped return can contain multiple serials.
6. Links every migrated return entry to its exact source through `dispatch_entry_id`.
7. Preserves each entry's legacy condition and note, along with the return customer, date, container, and source dispatch.
8. Uses the first entry in each group for the return header's preparer, approver, and audit timestamps; `received_by` falls back to `dispatched_by` when absent.
9. Marks migrated lost serials inactive.
10. Enforces required dispatch customer and removes the obsolete dispatch-entry return columns.

The removed public surface includes:

- `received_by`, `return_date`, `return_condition`, and `return_note` on dispatch entries.
- `ReceiveContainerReturnData` and `ReceiveContainerReturnAction`.
- Bulk receipt UI actions and forms.
- `DispatchReturnImporter`.
- `receive.dispatch-entries`.

The migration uses query-builder writes and does not emit current business events for historical rows. Its customer/date grouping cache spans all migration chunks, so entries in the same group still share a return document when they are processed in different batches.

The data-refactor migration intentionally has no `down()` implementation because the removed legacy columns cannot safely represent grouped return documents and their audit history. Treat the upgrade as a controlled production change, validate migrated document and entry totals, retain a restorable backup, and use a forward correction strategy after go-live.

## Internal Controls And Reversals

- Preparers cannot approve their own return documents.
- Submitted and approved documents cannot be edited.
- Approval requires exact outstanding source custody.
- Cross returns are system-derived and reportable.
- Lost serials are deactivated.
- Dispatches with posted return reconciliation cannot be voided.
- Approved returns have no reversal operation in this release.

If a posted return is incorrect, do not alter its rows directly. A future controlled reversal workflow or a host-approved compensating process must preserve the original audit trail.

## Testing And Quality

All package database tests explicitly run against in-memory SQLite:

```bash
composer test
```

Individual checks:

```bash
composer analyse
./vendor/bin/pint --test
./vendor/bin/rector process --dry-run
./vendor/bin/pest --type-coverage --compact --min=95
```

The complete quality pipeline applies configured Rector transformations and then runs Pint, PHPStan, the complete Pest suite, and the 95% type-coverage threshold:

```bash
composer lint
```

Validate migrations, locking behavior, query plans, and lifecycle integration against PostgreSQL in the host application's staging environment before production deployment.
