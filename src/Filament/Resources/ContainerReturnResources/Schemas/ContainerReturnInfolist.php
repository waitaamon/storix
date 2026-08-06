<?php

declare(strict_types=1);

namespace Storix\Filament\Resources\ContainerReturnResources\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ContainerReturnInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('code'),

                    TextEntry::make('customer.name')
                        ->label('Customer'),

                    TextEntry::make('transaction_date')
                        ->date(),

                    TextEntry::make('state'),

                    TextEntry::make('user.name')
                        ->label('Prepared By'),

                    TextEntry::make('approvedBy.name')
                        ->label('Approved By')
                        ->placeholder('—'),

                    TextEntry::make('approved_at')
                        ->dateTime()
                        ->placeholder('—'),

                    TextEntry::make('entries_count')
                        ->label('Entries'),

                    TextEntry::make('note')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
