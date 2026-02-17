<?php

declare(strict_types=1);

use Storix\Filament\Exports\ContainerExporter;
use Storix\Filament\Exports\DispatchExporter;
use Storix\Filament\Imports\ContainerImporter;
use Storix\Filament\Imports\DispatchImporter;
use Storix\Filament\Imports\DispatchReturnImporter;
use Storix\Models\DispatchEntry;

it('defines container import columns', function (): void {
    $columns = ContainerImporter::getColumns();

    expect($columns)->toHaveCount(4);
});

it('defines dispatch import and return import columns', function (): void {
    expect(DispatchImporter::getColumns())->toHaveCount(4)
        ->and(DispatchReturnImporter::getColumns())->toHaveCount(5);
});

it('defines export columns for containers and dispatches', function (): void {
    $dispatchColumns = collect(DispatchExporter::getColumns())
        ->map(static fn ($column): string => $column->getName())
        ->all();

    expect(ContainerExporter::getColumns())->not->toBeEmpty()
        ->and($dispatchColumns)->toContain('containers.serial')
        ->and($dispatchColumns)->toContain('entries.return_date')
        ->and($dispatchColumns)->toContain('entries.return_condition')
        ->and($dispatchColumns)->toContain('entries.return_note')
        ->and($dispatchColumns)->not->toContain('return_date')
        ->and($dispatchColumns)->not->toContain('return_condition')
        ->and($dispatchColumns)->not->toContain('return_note');
});

it('targets dispatch entries for return imports', function (): void {
    $reflection = new ReflectionClass(DispatchReturnImporter::class);
    $model = $reflection->getStaticPropertyValue('model');

    expect($model)->toBe(DispatchEntry::class);
});
