<?php

declare(strict_types=1);

use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Livewire\Component;
use Mockery\MockInterface;
use Storix\Filament\Exports\ContainerReturnEntryExporter;
use Storix\Filament\Exports\ContainerReturnExporter;
use Storix\Filament\Resources\ContainerResources\ContainerResource;
use Storix\Filament\Resources\ContainerResources\RelationManagers\ReturnsRelationManager;
use Storix\Filament\Resources\ContainerReturnEntriesResources\ContainerReturnEntryResource;
use Storix\Filament\Resources\ContainerReturnEntriesResources\Schemas\ContainerReturnEntryForm;
use Storix\Filament\Resources\ContainerReturnEntriesResources\Tables\ContainerReturnEntriesTable;
use Storix\Filament\Resources\ContainerReturnResources\ContainerReturnResource;
use Storix\Filament\Resources\ContainerReturnResources\RelationManagers\EntriesRelationManager;
use Storix\Filament\Resources\ContainerReturnResources\Schemas\ContainerReturnForm;
use Storix\Filament\Resources\ContainerReturnResources\Schemas\ContainerReturnInfolist;
use Storix\Filament\Resources\ContainerReturnResources\Tables\ContainerReturnsTable;
use Storix\Models\ContainerReturnEntry;

final class ContainerReturnSchemaTestComponent extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public function render(): string
    {
        return '<div></div>';
    }
}

it('configures return forms and infolists for the controlled document fields', function (): void {
    $form = ContainerReturnForm::configure(Schema::make(new ContainerReturnSchemaTestComponent));
    $infolist = ContainerReturnInfolist::configure(Schema::make(new ContainerReturnSchemaTestComponent));
    $formComponents = $form->getFlatComponents(withHidden: true);
    $infolistComponents = $infolist->getFlatComponents(withHidden: true);
    $customer = $formComponents['customer_id'] ?? null;
    $transactionDate = $formComponents['transaction_date'] ?? null;

    if (! $customer instanceof Select || ! $transactionDate instanceof DatePicker) {
        throw new LogicException('The required container return form fields are not configured.');
    }

    expect($customer->isRequired())->toBeTrue()
        ->and($transactionDate->isRequired())->toBeTrue()
        ->and($infolistComponents['code'] ?? null)->toBeInstanceOf(TextEntry::class)
        ->and($infolistComponents['customer.name'] ?? null)->toBeInstanceOf(TextEntry::class)
        ->and($infolistComponents['approvedBy.name'] ?? null)->toBeInstanceOf(TextEntry::class)
        ->and($infolistComponents['entries_count'] ?? null)->toBeInstanceOf(TextEntry::class);
});

it('configures return entry forms including reconciliation controls', function (): void {
    $form = ContainerReturnEntryForm::configure(Schema::make(new ContainerReturnSchemaTestComponent));
    $formComponents = $form->getFlatComponents(withHidden: true);

    expect($formComponents['container_return_id'] ?? null)->toBeInstanceOf(Select::class)
        ->and($formComponents['container_id'] ?? null)->toBeInstanceOf(Select::class)
        ->and($formComponents['return_condition'] ?? null)->toBeInstanceOf(Select::class);
});

it('configures return document filters, columns, and exporter', function (): void {
    /** @var HasTable&MockInterface $livewire */
    $livewire = Mockery::mock(HasTable::class);
    $table = ContainerReturnsTable::configure(Table::make($livewire));
    $export = $table->getBulkAction('export');

    if (! $export instanceof ExportBulkAction) {
        throw new LogicException('The container return export action is not configured.');
    }

    expect($table->getColumn('code'))->toBeInstanceOf(TextColumn::class)
        ->and($table->getColumn('customer.name'))->toBeInstanceOf(TextColumn::class)
        ->and($table->getColumn('entries_count'))->toBeInstanceOf(TextColumn::class)
        ->and(array_keys($table->getFilters()))->toBe(['customer', 'state', 'transaction_date'])
        ->and($table->getFilter('customer'))->toBeInstanceOf(SelectFilter::class)
        ->and($table->getFilter('state'))->toBeInstanceOf(SelectFilter::class)
        ->and($table->getFilter('transaction_date'))->toBeInstanceOf(Filter::class)
        ->and($export->getExporter())->toBe(ContainerReturnExporter::class);
});

it('configures return entry filters, columns, and exporter', function (): void {
    /** @var HasTable&MockInterface $livewire */
    $livewire = Mockery::mock(HasTable::class);
    $table = ContainerReturnEntriesTable::configure(Table::make($livewire));
    $export = $table->getBulkAction('export');
    $entry = ContainerReturnEntry::factory()->create();

    if (! $export instanceof ExportBulkAction) {
        throw new LogicException('The container return entry export action is not configured.');
    }

    expect($table->getColumn('containerReturn.code'))->toBeInstanceOf(TextColumn::class)
        ->and($table->getRecordUrl($entry))->toBe(ContainerReturnResource::getUrl('view', [
            'record' => $entry->containerReturn,
        ]))
        ->and(array_keys($table->getFlatRecordActions()))->toBe(['edit'])
        ->and($table->getColumn('container.serial'))->toBeInstanceOf(TextColumn::class)
        ->and($table->getColumn('cross_return'))->toBeInstanceOf(IconColumn::class)
        ->and(array_keys($table->getFilters()))->toBe([
            'customer',
            'state',
            'transaction_date',
            'return_condition',
            'cross_return',
        ])
        ->and($export->getExporter())->toBe(ContainerReturnEntryExporter::class);
});

it('registers full return resources and both return relation managers', function (): void {
    expect(ContainerReturnResource::getPages())->toHaveKeys(['index', 'create', 'view', 'edit'])
        ->and(array_keys(ContainerReturnEntryResource::getPages()))->toBe(['index', 'create', 'edit'])
        ->and(ContainerReturnResource::getRelations())->toContain(EntriesRelationManager::class)
        ->and(ContainerResource::getRelations())->toContain(ReturnsRelationManager::class)
        ->and(ContainerReturnResource::shouldRegisterNavigation())->toBeTrue()
        ->and(ContainerReturnEntryResource::shouldRegisterNavigation())->toBeTrue();
});
