<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerDispatches;

use BackedEnum;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Storix\Concerns\ResolvesCustomerConfig;
use Storix\Exports\ContainerDispatchExporter;
use Storix\Filament\Resources\ContainerDispatches\Pages\CreateContainerDispatch;
use Storix\Filament\Resources\ContainerDispatches\Pages\ListContainerDispatches;
use Storix\Imports\DispatchImporter;
use Storix\Models\Container;
use Storix\Models\ContainerDispatch;

final class ContainerDispatchResource extends Resource
{
    use ResolvesCustomerConfig;

    protected static ?string $model = ContainerDispatch::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    /** Define the dispatch creation form. */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')
                    ->searchable(self::customerSearchColumns())
                    ->preload()
                    ->relationship(name: 'customer', titleAttribute: self::customerTitleAttribute(), modifyQueryUsing: static fn (Builder $query): Builder => $query->orderBy(self::customerTitleAttribute()))
                    ->required(),

                TextInput::make('delivery_note_code')
                    ->required()
                    ->maxLength(255),

                DatePicker::make('transaction_date')
                    ->default(now())
                    ->native(false)
                    ->required(),

                Textarea::make('notes')
                    ->columnSpanFull()
                    ->rows(3),

                Select::make('container_ids')
                    ->label('Containers')
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->getSearchResultsUsing(static fn (string $search): array => self::searchDispatchableContainers($search))
                    ->getOptionLabelsUsing(static fn (array $values): array => self::containerLabels($values))
                    ->helperText('Only active containers not currently dispatched are shown.'),
            ]);
    }

    /** Define the dispatch listing table. */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_date')->date()->sortable(),

                TextColumn::make('delivery_note_code')->searchable()->sortable(),

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
                ImportAction::make()->importer(DispatchImporter::class),
                ExportAction::make()->exporter(ContainerDispatchExporter::class),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContainerDispatches::route('/'),
            'create' => CreateContainerDispatch::route('/create'),
        ];
    }

    public static function getNavigationGroup(): string
    {
        return 'Storix';
    }

    /**
     * Search for containers available for dispatch by serial or name.
     *
     * @return array<int, string>
     */
    private static function searchDispatchableContainers(string $search): array
    {
        return Container::query()
            ->availableForDispatch()
            ->where(static function (Builder $query) use ($search): void {
                $query
                    ->where('serial', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            })
            ->limit(50)
            ->get(['id', 'serial', 'name'])
            ->mapWithKeys(static fn (Container $container): array => [
                (int) $container->getKey() => sprintf('%s - %s', $container->serial, $container->name),
            ])
            ->all();
    }

    /**
     * Resolve display labels for selected container IDs.
     *
     * @param  array<int|string, mixed>  $values
     * @return array<int, string>
     */
    private static function containerLabels(array $values): array
    {
        $ids = array_values(array_filter(array_map(intval(...), $values)));

        if ($ids === []) {
            return [];
        }

        return Container::query()
            ->whereIn('id', $ids)
            ->get(['id', 'serial', 'name'])
            ->mapWithKeys(static fn (Container $container): array => [
                (int) $container->getKey() => sprintf('%s - %s', $container->serial, $container->name),
            ])
            ->all();
    }
}
