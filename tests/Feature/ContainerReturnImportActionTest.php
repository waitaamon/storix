<?php

declare(strict_types=1);

use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use Storix\Filament\Imports\ContainerReturnEntryImporter;
use Storix\Filament\Resources\ContainerReturnResources\Pages\ViewContainerReturn;
use Storix\Filament\Resources\ContainerReturnResources\RelationManagers\EntriesRelationManager;
use Storix\Models\Container;
use Storix\Models\ContainerReturn;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Tests\Fixtures\Models\User;

use function Pest\Laravel\actingAs;

it('configures an authorized import action for the owning draft return', function (): void {
    Gate::before(static fn (mixed $user, string $ability): ?bool => str_contains($ability, '.') ? true : null);
    Filament::setCurrentPanel('test');

    $user = User::query()->create([
        'name' => 'Authorized Return Importer',
        'email' => 'authorized-return-importer@example.com',
    ]);
    $containerReturn = ContainerReturn::factory()->draft()->create();

    actingAs($user);

    $component = Livewire::test(EntriesRelationManager::class, [
        'ownerRecord' => $containerReturn,
        'pageClass' => ViewContainerReturn::class,
    ])
        ->assertTableHeaderActionsExistInOrder(['import', 'create'])
        ->assertTableActionVisible('import')
        ->assertTableActionHasLabel('import', 'Import Entries')
        ->assertTableActionHasIcon('import', 'heroicon-o-document-arrow-up');
    $manager = $component->instance();

    if (! $manager instanceof EntriesRelationManager) {
        throw new LogicException('The Livewire component is not a return entries relation manager.');
    }

    $importAction = $manager->getTable()->getAction('import');
    $createAction = $manager->getTable()->getAction('create');

    expect($importAction)->toBeInstanceOf(ImportAction::class)
        ->and($createAction)->toBeInstanceOf(CreateAction::class);

    if (! $importAction instanceof ImportAction || ! $createAction instanceof CreateAction) {
        throw new LogicException('The expected return entry header actions are not configured.');
    }

    $createSchema = $createAction->getSchema(Schema::make($manager));
    $containerSelect = $createSchema?->getFlatComponents(withHidden: true)['container_id'] ?? null;

    expect($importAction->getImporter())->toBe(ContainerReturnEntryImporter::class)
        ->and($importAction->getOptions())->toBe(['container_return_id' => $containerReturn->id])
        ->and($containerSelect)->toBeInstanceOf(Select::class);

    if (! $containerSelect instanceof Select) {
        throw new LogicException('The container selector is not configured.');
    }

    expect($containerSelect->isSearchable())->toBeTrue()
        ->and($containerSelect->isPreloaded())->toBeFalse();
});

it('hides entry imports when the owning return is no longer editable', function (): void {
    Gate::before(static fn (mixed $user, string $ability): ?bool => str_contains($ability, '.') ? true : null);
    Filament::setCurrentPanel('test');

    $user = User::query()->create([
        'name' => 'Submitted Return User',
        'email' => 'submitted-return-user@example.com',
    ]);
    $containerReturn = ContainerReturn::factory()->submitted()->create();

    actingAs($user);

    Livewire::test(EntriesRelationManager::class, [
        'ownerRecord' => $containerReturn,
        'pageClass' => ViewContainerReturn::class,
    ])->assertTableActionHidden('import');
});

it('hides entry imports without both entry-create and return-update authorization', function (): void {
    Gate::before(static fn (mixed $user, string $ability): bool => $ability === 'view' || str_starts_with($ability, 'view.'));
    Filament::setCurrentPanel('test');

    $user = User::query()->create([
        'name' => 'Read Only Return User',
        'email' => 'read-only-return-user@example.com',
    ]);
    $containerReturn = ContainerReturn::factory()->draft()->create();

    actingAs($user);

    Livewire::test(EntriesRelationManager::class, [
        'ownerRecord' => $containerReturn,
        'pageClass' => ViewContainerReturn::class,
    ])->assertTableActionHidden('import');
});

it('imports an uploaded CSV through the relation manager action', function (): void {
    Gate::before(static fn (mixed $user, string $ability): ?bool => str_contains($ability, '.') ? true : null);
    Filament::setCurrentPanel('test');

    $user = User::query()->create([
        'name' => 'CSV Return Importer',
        'email' => 'csv-return-importer@example.com',
    ]);
    $container = Container::factory()->create();
    $dispatch = Dispatch::factory()->create([
        'quantity' => 1,
        'state' => 'approved',
        'approved_by' => $user->id,
        'approved_at' => now(),
    ]);
    DispatchEntry::factory()->create([
        'dispatch_id' => $dispatch->id,
        'container_id' => $container->id,
    ]);
    $containerReturn = ContainerReturn::factory()->draft()->create([
        'user_id' => $user->id,
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'container-return-entries.csv',
        "serial,return_condition,note\n{$container->serial},good,Received through Livewire\n",
    );
    $temporaryDisk = FileUploadConfiguration::disk();
    FileUploadConfiguration::storage();
    $storedPath = FileUploadConfiguration::storeTemporaryFile($file, $temporaryDisk);
    $temporaryPath = str_replace(FileUploadConfiguration::path('/'), '', $storedPath);
    $temporaryFile = new TemporaryUploadedFile($temporaryPath, $temporaryDisk);

    actingAs($user);

    $manager = Livewire::test(EntriesRelationManager::class, [
        'ownerRecord' => $containerReturn,
        'pageClass' => ViewContainerReturn::class,
    ])->instance();

    if (! $manager instanceof EntriesRelationManager) {
        throw new LogicException('The Livewire component is not a return entries relation manager.');
    }

    $importAction = $manager->getTable()->getAction('import');

    if (! $importAction instanceof ImportAction) {
        throw new LogicException('The return entry import action is not configured.');
    }

    $importAction->call(['data' => [
        'file' => $temporaryFile,
        'columnMap' => [
            'serial' => 'serial',
            'return_condition' => 'return_condition',
            'note' => 'note',
        ],
    ]]);

    $import = Import::query()->sole();
    $entry = $containerReturn->entries()->sole();

    expect($import->processed_rows)->toBe(1)
        ->and($import->successful_rows)->toBe(1)
        ->and($entry->container_id)->toBe($container->id)
        ->and($entry->note)->toBe('Received through Livewire');
});
