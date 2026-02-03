<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\Filament\Resources\ContainerDispatches;

use Filament\Actions\Exports\ExportAction;
use Filament\Actions\Imports\ImportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Storix\ContainerMovement\Exports\ContainerDispatchExporter;
use Storix\ContainerMovement\Filament\Resources\ContainerDispatches\Pages\CreateContainerDispatch;
use Storix\ContainerMovement\Filament\Resources\ContainerDispatches\Pages\ListContainerDispatches;
use Storix\ContainerMovement\Imports\DispatchImporter;
use Storix\ContainerMovement\Models\Container;
use Storix\ContainerMovement\Models\ContainerDispatch;

final class ContainerDispatchResource extends Resource
{
    protected static ?string $model = ContainerDispatch::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('customer_id')
                    ->relationship('customer', self::customerTitleAttribute(), modifyQueryUsing: static fn (Builder $query): Builder => $query
                        ->orderBy(self::customerTitleAttribute()))
                    ->searchable(self::customerSearchColumns())
                    ->preload()
                    ->required(),
                TextInput::make('sale_order_code')
                    ->required()
                    ->maxLength(255),
                DatePicker::make('transaction_date')
                    ->default(now())
                    ->native(false)
                    ->required(),
                Textarea::make('notes')
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_date')->date()->sortable(),
                TextColumn::make('sale_order_code')->searchable()->sortable(),
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

    public static function getNavigationGroup(): ?string
    {
        return 'Container Movement';
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

    /**
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
     * @param array<int|string, mixed> $values
     * @return array<int, string>
     */
    private static function containerLabels(array $values): array
    {
        $ids = array_values(array_filter(array_map('intval', $values)));

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
