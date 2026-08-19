<?php

declare(strict_types=1);

namespace Storix\Filament\Pages;

use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Config;
use Override;
use Storix\Filament\Tables\CustomerContainerBalancesTable;
use Storix\Permissions\StorixPermissions;
use UnitEnum;

final class CustomerContainerBalances extends Page implements HasTable
{
    use InteractsWithTable;

    #[Override]
    protected static ?string $title = 'Customer Container Balance Report';

    #[Override]
    protected static ?string $slug = 'customer-container-balances';

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Storix';

    #[Override]
    public static function getNavigationLabel(): string
    {
        return Config::string(
            'storix.navigation.customer_container_balances_label',
            'Customer Container Balances',
        );
    }

    #[Override]
    public static function canAccess(): bool
    {
        return auth()->user()?->can(
            StorixPermissions::VIEW_ANY_CUSTOMER_CONTAINER_BALANCES,
        ) ?? false;
    }

    public function table(Table $table): Table
    {
        return CustomerContainerBalancesTable::configure($table);
    }

    #[Override]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }
}
