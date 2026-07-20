<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchResources\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Storix\Models\Container;
use Storix\Support\FinancialYear;

final class DispatchForm
{
    public static function configure(Schema $schema): Schema
    {
        $year = FinancialYear::selected();

        return $schema->components([
            Section::make()
                ->columns()
                ->columnSpanFull()
                ->schema([

                    Select::make('delivery_note_id')
                        ->relationship(
                            name: 'deliveryNote',
                            titleAttribute: 'code',
                            modifyQueryUsing: fn ($query) => ($modifier = Config::get('storix.delivery_note_query_modifier'))
                                ? $modifier($query)->with('customer')
                                : $query->with('customer')

                        )
                        ->getOptionLabelFromRecordUsing(fn (Model $record): string => self::deliveryNoteLabel($record))
                        ->getSearchResultsUsing(function (string $search): array {
                            $model = Config::string('storix.models.delivery_note', 'App\\Models\\Sales\\DeliveryNote');

                            $query = $model::query()->with('customer');

                            if ($modifier = Config::get('storix.delivery_note_query_modifier')) {
                                $query = $modifier($query);
                            }

                            return $query
                                ->where(fn ($query) => $query
                                    ->where('code', 'like', "%{$search}%")
                                    ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', "%{$search}%")))
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (Model $record): array => [$record->getKey() => self::deliveryNoteLabel($record)])
                                ->all();
                        })
                        ->searchable()
                        ->preload()
                        ->required(),

                    DateTimePicker::make('dispatched_at')
                        ->default(now())
                        ->closeOnDateSelection()
                        ->native(false)
                        ->minDate($year?->start_date)
                        ->maxDate($year?->end_date)
                        ->required(),

                    TextInput::make('quantity')
                        ->integer()
                        ->minValue(1)
                        ->required(),

                    Select::make('container_ids')
                        ->label(str(Config::string('storix.labels.container'))->plural()->headline()->toString())
                        ->options(static fn (): array => Container::query()
                            ->availableForDispatch()
                            ->orderBy('serial')
                            ->pluck('serial', 'id')
                            ->all())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->visibleOn('create')
                        ->columnSpanFull(),

                    Textarea::make('dispatch_note')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    private static function deliveryNoteLabel(Model $record): string
    {
        $customer = $record->getRelationValue('customer');
        $customerName = $customer instanceof Model
            ? (string) $customer->getAttribute('name')
            : '';

        return $record->getAttribute('code').' - '.$customerName;
    }
}
