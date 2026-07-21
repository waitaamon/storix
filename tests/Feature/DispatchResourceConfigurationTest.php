<?php

declare(strict_types=1);

use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Livewire\Component;
use Mockery\MockInterface;
use Storix\Filament\Resources\DispatchResources\Schemas\DispatchForm;
use Storix\Filament\Resources\DispatchResources\Schemas\DispatchInfolist;
use Storix\Filament\Resources\DispatchResources\Tables\DispatchesTable;
use Storix\Support\TableNames;

final class DispatchResourceSchemaTestComponent extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public function render(): string
    {
        return '<div></div>';
    }
}

it('configures quantity as a required positive integer field', function (): void {
    $schema = DispatchForm::configure(Schema::make(new DispatchResourceSchemaTestComponent));
    $quantity = $schema->getFlatComponents(withHidden: true)['quantity'] ?? null;

    expect($quantity)->toBeInstanceOf(TextInput::class);

    assert($quantity instanceof TextInput);

    expect($quantity->isRequired())->toBeTrue()
        ->and($quantity->isNumeric())->toBeTrue()
        ->and($quantity->getStep())->toBe(1)
        ->and($quantity->getMinValue())->toBe(1)
        ->and($quantity->getValidationRules())->toContain('integer', 'min:1', 'required');
});

it('displays quantity in the dispatch infolist', function (): void {
    $schema = DispatchInfolist::configure(Schema::make(new DispatchResourceSchemaTestComponent));

    expect($schema->getFlatComponents(withHidden: true)['quantity'] ?? null)
        ->toBeInstanceOf(TextEntry::class);
});

it('displays declared and attached container quantities in the dispatch table', function (): void {
    /** @var HasTable&MockInterface $livewire */
    $livewire = Mockery::mock(HasTable::class);
    $table = DispatchesTable::configure(Table::make($livewire));
    $quantity = $table->getColumn('quantity');

    expect($quantity)->toBeInstanceOf(TextColumn::class)
        ->and($quantity?->isSortable())->toBeTrue()
        ->and($table->getColumn('containers_count'))->toBeInstanceOf(TextColumn::class);
});

it('configures only the required dispatch filters in their business order', function (): void {
    /** @var HasTable&MockInterface $livewire */
    $livewire = Mockery::mock(HasTable::class);
    $table = DispatchesTable::configure(Table::make($livewire));
    $filters = $table->getFilters();

    expect(array_keys($filters))->toBe(['customer', 'approved_at'])
        ->and($table->getFilter('customer'))->toBeInstanceOf(SelectFilter::class)
        ->and($table->getFilter('approved_at'))->toBeInstanceOf(Filter::class)
        ->and($table->getFilter('approved_at')?->getLabel())->toBe('Dispatch date');
});

it('indexes the approval timestamp used by the dispatch date filter', function (): void {
    expect(DatabaseSchema::hasIndex(TableNames::dispatches(), ['approved_at']))->toBeTrue();
});
