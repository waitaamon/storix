<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\DispatchResources\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class DispatchInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(4)
                ->schema([
                    TextEntry::make('code'),

                    TextEntry::make('deliveryNote.customer.name')
                        ->label('Customer'),

                    TextEntry::make('deliveryNote.code')
                        ->label('Delivery Note'),

                    TextEntry::make('dispatched_at')
                        ->date(),

                    TextEntry::make('quantity'),

                    TextEntry::make('dispatchedBy.name')
                        ->label('Dispatched By'),

                    TextEntry::make('state'),

                    TextEntry::make('approvedBy.name')
                        ->label('Approved By'),

                    TextEntry::make('approved_at')->dateTime(),

                    TextEntry::make('voidedBy.name')
                        ->label('Voided By'),

                    TextEntry::make('voided_at')->dateTime(),

                    TextEntry::make('created_at')->dateTime(),

                    TextEntry::make('dispatch_note')
                        ->columnSpanFull(),

                    TextEntry::make('void_reason')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
