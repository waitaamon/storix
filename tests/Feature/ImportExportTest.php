<?php

declare(strict_types=1);

use Storix\Filament\Exports\ContainerExporter;
use Storix\Filament\Exports\DispatchExporter;
use Storix\Filament\Imports\ContainerImporter;
use Storix\Filament\Imports\DispatchImporter;
use Storix\Filament\Imports\DispatchReturnImporter;

it('defines container import columns', function (): void {
    $columns = ContainerImporter::getColumns();

    expect($columns)->toHaveCount(4);
});

it('defines dispatch import and return import columns', function (): void {
    expect(DispatchImporter::getColumns())->toHaveCount(6)
        ->and(DispatchReturnImporter::getColumns())->toHaveCount(5);
});

it('defines export columns for containers and dispatches', function (): void {
    expect(ContainerExporter::getColumns())->not->toBeEmpty()
        ->and(DispatchExporter::getColumns())->not->toBeEmpty();
});
