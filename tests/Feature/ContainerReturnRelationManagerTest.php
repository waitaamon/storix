<?php

declare(strict_types=1);

use Filament\Actions\ExportBulkAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Storix\Filament\Exports\ContainerReturnEntryExporter;
use Storix\Filament\Resources\ContainerResources\Pages\ViewContainer;
use Storix\Filament\Resources\ContainerResources\RelationManagers\ReturnsRelationManager;
use Storix\Models\Container;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Tests\Fixtures\Models\User;

use function Pest\Laravel\actingAs;

it('shows immutable return history and scoped exports on a container', function (): void {
    Gate::before(static fn (mixed $user, string $ability): ?bool => str_contains($ability, '.') ? true : null);
    Filament::setCurrentPanel('test');

    $user = User::query()->create(['name' => 'History User', 'email' => 'return-history@example.com']);
    $container = Container::factory()->create();
    $otherContainer = Container::factory()->create();
    $containerReturn = ContainerReturn::factory()->approved()->create(['user_id' => $user->id]);
    $historyEntry = ContainerReturnEntry::factory()->crossReturn()->create([
        'container_return_id' => $containerReturn->id,
        'container_id' => $container->id,
    ]);
    ContainerReturnEntry::factory()->create([
        'container_return_id' => $containerReturn->id,
        'container_id' => $otherContainer->id,
    ]);

    actingAs($user);

    $manager = Livewire::test(ReturnsRelationManager::class, [
        'ownerRecord' => $container,
        'pageClass' => ViewContainer::class,
    ])->instance();

    if (! $manager instanceof ReturnsRelationManager) {
        throw new LogicException('The Livewire component is not a returns relation manager.');
    }

    $table = $manager->getTable();
    $export = $table->getBulkAction('export');

    if (! $export instanceof ExportBulkAction) {
        throw new LogicException('The return history export action is not configured.');
    }

    expect($manager->isReadOnly())->toBeTrue()
        ->and($table->getColumn('containerReturn.code'))->toBeInstanceOf(TextColumn::class)
        ->and($table->getColumn('containerReturn.customer.name'))->toBeInstanceOf(TextColumn::class)
        ->and($table->getColumn('return_condition'))->toBeInstanceOf(TextColumn::class)
        ->and($table->getColumn('cross_return'))->toBeInstanceOf(IconColumn::class)
        ->and($export->getExporter())->toBe(ContainerReturnEntryExporter::class)
        ->and($manager->getTableQueryForExport()->pluck('id')->all())->toBe([$historyEntry->id]);
});
