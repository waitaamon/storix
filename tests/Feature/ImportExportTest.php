<?php

declare(strict_types=1);

use Storix\Filament\Exports\ContainerExporter;
use Storix\Filament\Exports\ContainerReturnEntryExporter;
use Storix\Filament\Exports\ContainerReturnExporter;
use Storix\Filament\Exports\DispatchEntryExporter;
use Storix\Filament\Exports\DispatchExporter;
use Storix\Filament\Imports\ContainerImporter;
use Storix\Filament\Imports\DispatchEntryImporter;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\DispatchEntry;

it('defines container and dispatch-entry import columns', function (): void {
    expect(ContainerImporter::getColumns())->toHaveCount(6)
        ->and(DispatchEntryImporter::getColumns())->toHaveCount(1);
});

it('exports dispatches through their direct customer relationship', function (): void {
    $columns = collect(DispatchExporter::getColumns())
        ->map(static fn ($column): string => $column->getName())
        ->all();

    expect(ContainerExporter::getColumns())->not->toBeEmpty()
        ->and($columns)->toContain('customer.name', 'deliveryNote.code', 'quantity', 'state')
        ->and($columns)->not->toContain('deliveryNote.customer.name', 'containers.serial');
});

it('exports dispatch entries as movement records without legacy return fields', function (): void {
    $columns = collect(DispatchEntryExporter::getColumns())
        ->map(static fn ($column): string => $column->getName())
        ->all();

    expect(DispatchEntryExporter::getModel())->toBe(DispatchEntry::class)
        ->and($columns)->toBe([
            'container.serial',
            'container.name',
            'container.replacement_cost',
            'container.replacement_currency',
            'dispatch.customer.name',
            'dispatch.deliveryNote.code',
            'dispatch.dispatched_at',
            'dispatch.dispatch_note',
        ]);
});

it('exports return documents and reconciliation entries separately', function (): void {
    $documentColumns = collect(ContainerReturnExporter::getColumns())
        ->map(static fn ($column): string => $column->getName())
        ->all();
    $entryColumns = collect(ContainerReturnEntryExporter::getColumns())
        ->map(static fn ($column): string => $column->getName())
        ->all();

    expect(ContainerReturnExporter::getModel())->toBe(ContainerReturn::class)
        ->and(ContainerReturnEntryExporter::getModel())->toBe(ContainerReturnEntry::class)
        ->and($documentColumns)->toContain(
            'code',
            'customer.name',
            'transaction_date',
            'state',
            'entries_count',
        )
        ->and($entryColumns)->toContain(
            'containerReturn.code',
            'container.serial',
            'return_condition',
            'dispatchEntry.dispatch.code',
            'cross_return',
        );
});
