<?php

declare(strict_types=1);

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Models\Import;
use Storix\Filament\Imports\ContainerReturnEntryImporter;
use Storix\Models\ContainerReturnEntry;

it('defines the controlled return entry import contract', function (): void {
    $columns = collect(ContainerReturnEntryImporter::getColumns())->keyBy(
        static fn (ImportColumn $column): string => $column->getName(),
    );
    $serialColumn = $columns->get('serial');
    $conditionColumn = $columns->get('return_condition');

    if (! $serialColumn instanceof ImportColumn || ! $conditionColumn instanceof ImportColumn) {
        throw new LogicException('The return entry importer columns are incomplete.');
    }

    expect(ContainerReturnEntryImporter::getModel())->toBe(ContainerReturnEntry::class)
        ->and($columns->keys()->all())->toBe(['serial', 'return_condition', 'note'])
        ->and($serialColumn->isMappingRequired())->toBeTrue()
        ->and($conditionColumn->isMappingRequired())->toBeFalse()
        ->and(ContainerReturnEntryImporter::shouldPreventFormulaInjection())->toBeTrue();
});

it('reports successful and failed row totals in its completion message', function (): void {
    $import = new Import([
        'successful_rows' => 2,
        'total_rows' => 3,
    ]);

    expect(ContainerReturnEntryImporter::getCompletedNotificationBody($import))
        ->toContain('2 rows imported', '1 row failed to import');
});
