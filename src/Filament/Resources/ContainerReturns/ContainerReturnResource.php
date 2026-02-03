<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerReturns;

use BackedEnum;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Storix\Concerns\ResolvesCustomerConfig;
use Storix\Enums\ContainerConditionStatus;
use Storix\Exports\ContainerReturnExporter;
use Storix\Filament\Resources\ContainerReturns\Pages\CreateContainerReturn;
use Storix\Filament\Resources\ContainerReturns\Pages\ListContainerReturns;
use Storix\Imports\ReturnImporter;
use Storix\Models\ContainerDispatchItem;
use Storix\Models\ContainerReturn;

final class ContainerReturnResource extends Resource
{
    use ResolvesCustomerConfig;

    protected static ?string $model = ContainerReturn::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-uturn-left';

    /** Define the return creation form with customer auto-fill. */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('customer_id')
                    ->relationship(name: 'customer', titleAttribute: self::customerTitleAttribute(), modifyQueryUsing: static fn (Builder $query): Builder => $query
                        ->orderBy(self::customerTitleAttribute()))
                    ->searchable(self::customerSearchColumns())
                    ->preload()
                    ->live()
                    ->afterStateUpdated(static function (Set $set, mixed $state): void {
                        if (! is_numeric($state)) {
                            $set('items', []);

                            return;
                        }

                        $set('items', self::defaultItemsForCustomer((int) $state));
                    })
                    ->required(),
                DatePicker::make('transaction_date')
                    ->default(now())
                    ->native(false)
                    ->required(),
                Textarea::make('notes')
                    ->rows(3),
                Repeater::make('items')
                    ->schema([
                        TextInput::make('container_serial')
                            ->required()
                            ->maxLength(255)
                            ->datalist(static fn (Get $get): array => self::openContainerSerialsForCustomer($get('../../customer_id'))),
                        Select::make('condition_status')
                            ->options(array_combine(ContainerConditionStatus::values(), ContainerConditionStatus::values()))
                            ->default(ContainerConditionStatus::Good->value)
                            ->required(),
                        Textarea::make('notes')->rows(2),
                    ])
                    ->helperText('When a customer is selected, open dispatched containers are auto-filled.')
                    ->minItems(1)
                    ->defaultItems(1)
                    ->columns(1)
                    ->required(),
            ]);
    }

    /** Define the return listing table. */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_date')->date()->sortable(),
                TextColumn::make('customer.'.self::customerTitleAttribute())->label('Customer')->searchable(),
                TextColumn::make('items.container.serial')
                    ->label('Container Serials')
                    ->listWithLineBreaks()
                    ->limitList(3),
                TextColumn::make('items_count')->counts('items')->label('Containers'),
                TextColumn::make('created_at')->dateTime()->since(),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->headerActions([
                ImportAction::make()->importer(ReturnImporter::class),
                ExportAction::make()->exporter(ContainerReturnExporter::class),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContainerReturns::route('/'),
            'create' => CreateContainerReturn::route('/create'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Storix';
    }

    /**
     * Pre-fill the repeater with all open dispatch items for a customer.
     *
     * @return list<array{container_serial: string, condition_status: string, notes: null}>
     */
    private static function defaultItemsForCustomer(int $customerId): array
    {
        return ContainerDispatchItem::query()
            ->open()
            ->whereHas('dispatch', static fn (Builder $query): Builder => $query->where('customer_id', $customerId))
            ->with('container')
            ->orderByDesc('id')
            ->get()
            ->map(static fn (ContainerDispatchItem $item): array => [
                'container_serial' => (string) $item->container?->serial,
                'condition_status' => ContainerConditionStatus::Good->value,
                'notes' => null,
            ])
            ->filter(static fn (array $item): bool => $item['container_serial'] !== '')
            ->values()
            ->all();
    }

    /**
     * Get serials of containers currently dispatched to a customer, for the datalist.
     *
     * @return list<string>
     */
    private static function openContainerSerialsForCustomer(mixed $customerId): array
    {
        if (! is_numeric($customerId)) {
            return [];
        }

        return ContainerDispatchItem::query()
            ->open()
            ->whereHas('dispatch', static fn (Builder $query): Builder => $query->where('customer_id', (int) $customerId))
            ->with('container')
            ->orderByDesc('id')
            ->get()
            ->map(static fn (ContainerDispatchItem $item): string => (string) $item->container?->serial)
            ->filter(static fn (string $serial): bool => $serial !== '')
            ->unique()
            ->values()
            ->all();
    }
}
