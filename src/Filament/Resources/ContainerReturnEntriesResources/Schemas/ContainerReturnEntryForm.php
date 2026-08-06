<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerReturnEntriesResources\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Storix\Enums\ReturnCondition;
use Storix\Models\Container;
use Storix\Models\ContainerReturn;
use Storix\Models\States\ContainerReturnDraftState;

final class ContainerReturnEntryForm
{
    public static function configure(Schema $schema, bool $includeReturn = true): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('container_return_id')
                        ->label('Container Return')
                        ->relationship(
                            name: 'containerReturn',
                            titleAttribute: 'code',
                            modifyQueryUsing: fn (Builder $query): Builder => $query
                                ->whereState('state', ContainerReturnDraftState::class)
                                ->with('customer'),
                        )
                        ->getOptionLabelFromRecordUsing(
                            fn (ContainerReturn $record): string => "{$record->code} — {$record->customer->getAttribute('name')}",
                        )
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabledOn('edit')
                        ->visible($includeReturn),

                    Select::make('container_id')
                        ->relationship(
                            name: 'container',
                            titleAttribute: 'serial',
                            modifyQueryUsing: fn (Builder $query): Builder => $query
                                ->whereIn('id', Container::query()->currentlyDispatched()->select('id'))
                                ->orderBy('serial'),
                        )
                        ->searchable(['serial', 'name'])
                        ->preload()
                        ->required(),

                    Select::make('return_condition')
                        ->options(ReturnCondition::class)
                        ->default(ReturnCondition::Good)
                        ->required(),

                    Textarea::make('note')
                        ->maxLength(2000)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
