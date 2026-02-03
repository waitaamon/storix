<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\Filament\Resources\ContainerReturns;

use Filament\Actions\Exports\ExportAction;
use Filament\Actions\Imports\ImportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Storix\ContainerMovement\Enums\ContainerConditionStatus;
use Storix\ContainerMovement\Exports\ContainerReturnExporter;
use Storix\ContainerMovement\Filament\Resources\ContainerReturns\Pages\CreateContainerReturn;
use Storix\ContainerMovement\Filament\Resources\ContainerReturns\Pages\ListContainerReturns;
use Storix\ContainerMovement\Imports\ReturnImporter;
use Storix\ContainerMovement\Models\ContainerDispatchItem;
use Storix\ContainerMovement\Models\ContainerReturn;

final class ContainerReturnResource extends Resource
{
    protected static ?string $model = ContainerReturn::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-uturn-left';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('customer_id')
                    ->relationship('customer', self::customerTitleAttribute(), modifyQueryUsing: static fn (Builder $query): Builder => $query
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
                            ->datalist(static fn (Get $get): array => self::openContainerSerialsForCustomer($get('customer_id'))),
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
        return 'Container Movement';
    }

    /**
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

    /**
     * @return list<string>
     */
    private static function customerSearchColumns(): array
    {
        $columns = config('container-movement.customer_search_columns', ['name']);

        if (! is_array($columns) || $columns === []) {
            return ['name'];
        }

        return array_values(array_filter(array_map(static fn (mixed $column): string => (string) $column, $columns)));
    }

    private static function customerTitleAttribute(): string
    {
        return (string) config('container-movement.customer_title_attribute', 'name');
    }
}
