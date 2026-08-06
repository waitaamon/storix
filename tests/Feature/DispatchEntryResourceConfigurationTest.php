<?php

declare(strict_types=1);

use Filament\Actions\ExportBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Mockery\MockInterface;
use Storix\Filament\Exports\DispatchEntryExporter;
use Storix\Filament\Resources\DispatchEntriesResources\DispatchEntryResource;
use Storix\Filament\Resources\DispatchResources\DispatchResource;
use Storix\Models\DispatchEntry;

it('presents dispatch entries as read only movement records', function (): void {
    /** @var HasTable&MockInterface $livewire */
    $livewire = Mockery::mock(HasTable::class);
    $table = DispatchEntryResource::table(Table::make($livewire));
    $dispatchEntry = DispatchEntry::factory()->create();

    expect($table->getRecordActions())->toBe([])
        ->and($table->getRecordUrl($dispatchEntry))->toBe(DispatchResource::getUrl('view', [
            'record' => $dispatchEntry->dispatch,
        ]))
        ->and($table->getColumn('dispatch.customer.name'))->toBeInstanceOf(TextColumn::class)
        ->and($table->getColumn('return_date'))->toBeNull()
        ->and($table->getColumn('return_condition'))->toBeNull()
        ->and($table->getColumn('receivedBy.name'))->toBeNull();
});

it('keeps only dispatch movement filters and exports', function (): void {
    /** @var HasTable&MockInterface $livewire */
    $livewire = Mockery::mock(HasTable::class);
    $table = DispatchEntryResource::table(Table::make($livewire));
    $filters = $table->getFilters();
    $export = $table->getBulkAction('export');

    if (! $export instanceof ExportBulkAction) {
        throw new LogicException('The dispatch entry export action is not configured.');
    }

    expect(array_keys($filters))->toBe(['customer', 'approved_at'])
        ->and($table->getFilter('customer'))->toBeInstanceOf(SelectFilter::class)
        ->and($table->getFilter('approved_at'))->toBeInstanceOf(Filter::class)
        ->and($table->getFilter('return_condition'))->toBeNull()
        ->and($table->getFilter('return_date'))->toBeNull()
        ->and($export->getExporter())->toBe(DispatchEntryExporter::class);
});
