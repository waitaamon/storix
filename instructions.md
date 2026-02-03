# Filament Storix Plugin

## Laravel 12 + Filament v4 + Livewire v3 + PestPHP v4

------------------------------------------------------------------------

## 📌 Overview

This document defines the functional and technical specification for a
**Filament Plugin** that manages **container lifecycle and movement
between company and customers**.

The plugin supports:

- Container Registration
- Container Dispatch to Customers
- Container Return Tracking
- Excel Import for Dispatch and Returns
- Table Export using Filament Export Actions

------------------------------------------------------------------------

## 🧱 Technology Stack

Layer Technology
  -------------- -----------------------------
Backend Laravel 12.x
Language PHP 8.3+
Admin Panel Filament v4
Realtime Livewire v3
Testing PestPHP v4
Database MySQL / PostgreSQL
Architecture Service Layer + DTO Pattern

------------------------------------------------------------------------

## 🎯 Business Objective

Track reusable containers moving between:

Warehouse → Customer → Warehouse (Return)

Maintain full historical movement records.

------------------------------------------------------------------------

## 🗄 Database Design

### Containers Table

Column Type
  ------------- -----------------
id Bigint
name String
serial String (Unique)
is_active Boolean
timestamps Yes
softDeletes Yes

------------------------------------------------------------------------

### Container Dispatches Table

Column Type
  ----------------- ------------------
id Primary
customer_id FK
delivery_note_code String (Indexed)
transaction_date Date
user_id User FK Nullable
notes Text Nullable
attachments JSON Nullable
timestamps Yes
softDeletes Yes

------------------------------------------------------------------------
### Container Dispatch Items Table

Column Type
  ----------------- ------------------
id Primary
dispatch_id FK
container_id FK
timestamps Yes
softDeletes Yes

------------------------------------------------------------------------

### Container Returns Table

Column Type
  ----------------------- ----------------------------
id Primary
customer_id FK
transaction_date Date
user_id User FK Nullable
notes Text Nullable
attachments JSON Nullable
created_at Timestamp

------------------------------------------------------------------------
### Container Return Items Table

Column Type
  ----------------- ------------------
id Primary
return_id FK
container_id FK
notes Text Nullable
timestamps Yes
softDeletes Yes

------------------------------------------------------------------------

## 🧠 Business Rules

### Container Registration

- Serial must be unique
- Containers can be active or inactive
- Soft delete supported

------------------------------------------------------------------------

### Dispatch Rules

- Cannot dispatch inactive container
- Cannot dispatch container already dispatched and not returned
- Required fields:
    - Customer
    - Sale Order Code
    - Dispatch Date
    - Container Serial

------------------------------------------------------------------------

### Return Rules
- Cannot return container not dispatched
- Prevent double return
- Capture container condition

------------------------------------------------------------------------

## 🖥 Filament Resources

### 1️⃣ Containers Resource

Features: - CRUD - Active toggle - Serial uniqueness validation - Export
support

------------------------------------------------------------------------

### 2️⃣ Container Dispatch Resource

Features: - Dispatch form - Container serial lookup / search - Customer
relation select - Sale Order Code input - Date picker - Excel Import
(Filament ImportAction) - Export support

------------------------------------------------------------------------

### 3️⃣ Container Returns Resource

Features: - Select customer record - Auto-fill containers
Return date input - Condition status select - Excel Import support -
Export support

------------------------------------------------------------------------

## 📥 Excel Import Requirements

### Dispatch Import Columns

container_serial customer_name delivery_note_code
dispatch_date notes (optional)

------------------------------------------------------------------------

### Return Import Columns

container_serial return_date condition_status notes (optional)

------------------------------------------------------------------------

## 📤 Export Requirements

Export must be available for:

- Containers Master
- Dispatch History
- Return History

Implementation: - Filament ExportAction - Queueable exports recommended

------------------------------------------------------------------------

## 🏗 Architecture

### Service Classes

ContainerDispatchService ContainerReturnService
StorixValidator

------------------------------------------------------------------------

### DTOs

DispatchContainerDTO ReturnContainerDTO

------------------------------------------------------------------------

### Optional Domain Events

ContainerDispatched ContainerReturned

------------------------------------------------------------------------

## 🧪 Testing Requirements (PestPHP v4)

### Feature Tests

- Container creation
- Container dispatch
- Prevent duplicate dispatch
- Container return
- Prevent duplicate return
- Excel dispatch import
- Excel return import

------------------------------------------------------------------------

### Unit Tests

- Service classes
- Validators
- DTO mapping

------------------------------------------------------------------------

## 🎨 Filament UX Enhancements

- Use Filament v4 native actions
- ImportAction for Excel uploads
- ExportAction for data exports

### Suggested Dashboard Widgets

- Containers Currently With Customers
- Overdue Returns
- Dispatch vs Return Trend Chart

------------------------------------------------------------------------

## ⚡ Performance Considerations

- Index serial fields
- Optimize relationship eager loading
- Bulk insert for imports
- Queue export jobs

------------------------------------------------------------------------

## 📁 Expected Deliverables

- Plugin folder structure
- Database migrations
- Eloquent models
- Filament resources
- Import classes
- Export classes
- Service classes
- DTO classes
- Pest test suite
- Seeders with sample container data

------------------------------------------------------------------------

## ⭐ Optional Enhancements

- Container movement timeline view
- Barcode / QR Code serial support
- Overdue return alerts
- Mobile API endpoints for scanning apps

------------------------------------------------------------------------

## 🧾 Code Quality Standards

- Strict Types Enabled
- Laravel Pint formatting
- High PHPStan level
- SOLID principles
- Clean architecture separation

------------------------------------------------------------------------

## 🏁 Conclusion

This plugin should be designed as an **ERP-grade reusable Filament
module** capable of supporting high-volume container tracking across
multiple customers.

------------------------------------------------------------------------
