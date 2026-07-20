<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Actions\Exports\ExportColumn;
use Storix\Filament\Exports\ContainerExporter;
use Storix\Filament\Exports\DispatchEntryExporter;
use Storix\Filament\Exports\DispatchExporter;
use Storix\Filament\Imports\ContainerImporter;
use Storix\Filament\Imports\DispatchEntryImporter;
use Storix\Filament\Imports\DispatchReturnImporter;
use Storix\Models\DispatchEntry;

it('defines container import columns', function (): void {
    $columns = ContainerImporter::getColumns();

    expect($columns)->toHaveCount(6);
});

it('defines dispatch import and return import columns', function (): void {
    expect(DispatchEntryImporter::getColumns())->toHaveCount(1)
        ->and(DispatchReturnImporter::getColumns())->toHaveCount(4);
});

it('defines export columns for containers and dispatches', function (): void {
    $dispatchColumns = collect(DispatchExporter::getColumns())
        ->map(static fn ($column): string => $column->getName())
        ->all();

    expect(ContainerExporter::getColumns())->not->toBeEmpty()
        ->and($dispatchColumns)->toContain('deliveryNote.customer.name')
        ->and($dispatchColumns)->toContain('quantity')
        ->and($dispatchColumns)->toContain('state')
        ->and($dispatchColumns)->not->toContain('containers.serial')
        ->and($dispatchColumns)->not->toContain('entries.return_date');
});

it('defines the dispatch entry export contract', function (): void {
    $columns = collect(DispatchEntryExporter::getColumns());
    $dispatchEntryColumns = $columns
        ->map(static fn ($column): string => $column->getName())
        ->all();
    $returnDateColumn = $columns->first(
        static fn (ExportColumn $column): bool => $column->getName() === 'return_date',
    );

    expect(DispatchEntryExporter::getModel())->toBe(DispatchEntry::class)
        ->and($dispatchEntryColumns)->toBe([
            'container.serial',
            'container.name',
            'container.replacement_cost',
            'container.replacement_currency',
            'dispatch.deliveryNote.customer.name',
            'dispatch.deliveryNote.code',
            'dispatch.dispatched_at',
            'dispatch.dispatch_note',
            'receivedBy.name',
            'return_date',
            'return_condition',
            'return_note',
        ])
        ->and($returnDateColumn)->toBeInstanceOf(ExportColumn::class);

    if (! $returnDateColumn instanceof ExportColumn) {
        throw new LogicException('The return date export column is not configured.');
    }

    expect($returnDateColumn->formatState(CarbonImmutable::parse('2026-02-15 16:45:30')))
        ->toBe('2026-02-15')
        ->and($returnDateColumn->formatState(null))->toBeNull();
});

it('targets dispatch entries for return imports', function (): void {
    $reflection = new ReflectionClass(DispatchReturnImporter::class);
    $model = $reflection->getStaticPropertyValue('model');

    expect($model)->toBe(DispatchEntry::class);
});
