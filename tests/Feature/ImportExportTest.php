<?php

declare(strict_types=1);

use Storix\Filament\Exports\ContainerExporter;
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

it('targets dispatch entries for return imports', function (): void {
    $reflection = new ReflectionClass(DispatchReturnImporter::class);
    $model = $reflection->getStaticPropertyValue('model');

    expect($model)->toBe(DispatchEntry::class);
});
