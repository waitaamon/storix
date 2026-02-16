w# Storix -- Filament v4 Container Lifecycle Plugin -- AI Code Generation Prompt

## Package Name

**Package:** storix\
**Composer Vendor Example:** waitaamon/storix

------------------------------------------------------------------------

## Context

Storix is an ERP-grade reusable asset lifecycle plugin.

I have a product that is delivered to customers using reusable
containers. The lifecycle is:

1.  Product is dispatched to customer inside a container.
2.  Customer uses the product.
3.  Customer returns the container after use.
4.  Container condition is inspected and recorded.

Storix must be designed to support future reusable asset types beyond
containers (crates, cylinders, pallets, etc).

------------------------------------------------------------------------

## Technical Stack Requirements

Build a **production-grade Laravel Filament v4 plugin (Storix)** with:

-   Laravel 12+
-   PHP 8.5+
-   FilamentPHP v4
-   PestPHP v4 tests
-   PostgreSQL compatibility (STRICT --- must support UUIDs, JSONB,
    proper indexing, constraints)
-   Spatie Laravel Permission integration
-   Filament Bulk Import & Export (Excel)

------------------------------------------------------------------------

## Core Functional Requirements

### 1. Container Management

Create a **Containers module** with fields:

-   name (string, unique, indexed)
-   serial (string, unique, indexed)
-   is_active (boolean, default true)
-   description (text, nullable)

Requirements: - Filament Resource - PostgreSQL optimized migration -
Factory + Seeder - Pest tests - Soft deletes

------------------------------------------------------------------------

### 2. Dispatch / Return Lifecycle Tracking

Create **Dispatches module** representing full lifecycle.

### Dispatch Migration Fields

-   id
-   customer_id → FK customers table
-   container_id → FK containers table
-   dispatched_by → FK users table
-   delivery_note → string / text
-   received_by → FK users table (nullable)
-   dispatched_at → timestamp
-   dispatched_note → text (nullable)

### Return Tracking Fields

-   return_date → timestamp nullable
-   return_condition → nullabe string
-   return_note → text nullable

------------------------------------------------------------------------

## Database Requirements (PostgreSQL)

Must include:

-   Proper foreign keys with cascade rules
-   Indexes on:
    -   container_id
    -   customer_id
    -   dispatched_at
    -   return_date

------------------------------------------------------------------------

## Filament UI Requirements

### Containers Resource

Include:

-   List page
-   Create page
-   Edit page
-   View page

Add **Relation Manager** - DispatchesRelationManager - Shows full
dispatch + return history

------------------------------------------------------------------------

### Dispatch Resource

Include:

-   Dispatch creation form
-   Return update form section
-   Status indicators:
    -   Dispatched
    -   Returned Good
    -   Returned Damaged
    -   Lost

------------------------------------------------------------------------

## Bulk Data Features

### Excel Export

Must support bulk export for:

-   Containers
-   Dispatches

Using Filament export actions.

------------------------------------------------------------------------

### Bulk Import (Filament v4 Native or Custom Importers)

Must support:

#### Containers Import

-   Create container records

#### Dispatch Import

-   Create dispatch records

#### Return Import

-   Update existing dispatch return fields

Must include validation + failure reporting.

------------------------------------------------------------------------

## Authorization & Security

### Policies Required

#### Container Policy

Must support:

-   viewAny.containers
-   view.containers
-   create.containers
-   update.containers
-   delete.containers
-   restore.containers
-   forceDelete.containers

------------------------------------------------------------------------

#### Dispatch Policy

Must support:

-   viewAny.dispatches
-   view.dispatches
-   create.dispatches
-   update.dispatches
-   delete.dispatches
-   restore.dispatches
-   forceDelete.dispatches
-   receive.containers

------------------------------------------------------------------------

## Spatie Permission Integration

Automatically register permissions during Storix plugin install.

------------------------------------------------------------------------

## Testing Requirements

Using **PestPHP v4**:

-   Container CRUD tests
-   Dispatch lifecycle tests
-   Import tests
-   Export tests
-   Policy authorization tests

------------------------------------------------------------------------

## Code Quality

Must include:

-   PHPStan / Larastan ready
-   Pint formatting
-   Strict types everywhere
-   DTO usage where appropriate
-   Service layer for lifecycle logic

------------------------------------------------------------------------

## Deliverables

Storix plugin must include:

-   Migrations
-   Models
-   Policies
-   Filament Resources
-   Relation Managers
-   Importers
-   Exporters
-   Factories
-   Seeders
-   Pest Tests
-   Service Classes
-   Permission Seeder
-   README

------------------------------------------------------------------------

## Bonus

-   Container utilization dashboard widgets
-   Damage rate analytics
-   Customer container aging report
-   Lost container financial exposure report

------------------------------------------------------------------------

## Expected Output

Generate complete **Storix plugin scaffold** with production-quality
architecture.

Follow best practices for: - Large ERP systems - Auditability -
Financial traceability - High data integrity
