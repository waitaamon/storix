<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Schema;
use Spatie\ModelStates\Exceptions\TransitionNotFound;
use Storix\Enums\ReturnCondition;
use Storix\Filament\Resources\ContainerReturnEntriesResources\ContainerReturnEntryResource;
use Storix\Filament\Resources\ContainerReturnResources\ContainerReturnResource;
use Storix\Models\Container;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\States\ContainerReturnApprovedState;
use Storix\Models\States\ContainerReturnDraftState;
use Storix\Models\States\ContainerReturnSubmittedState;
use Storix\Permissions\StorixPermissions;
use Storix\Policies\ContainerReturnEntryPolicy;
use Storix\Policies\ContainerReturnPolicy;
use Storix\Support\TableNames;
use Storix\Tests\Fixtures\Models\Customer;
use Storix\Tests\Fixtures\Models\User;

it('creates the indexed container return schema', function (): void {
    expect(Schema::hasColumns(TableNames::containerReturns(), [
        'id',
        'code',
        'customer_id',
        'user_id',
        'approved_by',
        'approved_at',
        'note',
        'state',
        'transaction_date',
        'deleted_at',
    ]))->toBeTrue()
        ->and(Schema::hasIndex(TableNames::containerReturns(), ['code']))->toBeTrue()
        ->and(Schema::hasIndex(TableNames::containerReturns(), ['customer_id', 'state', 'transaction_date']))->toBeTrue()
        ->and(Schema::hasColumns(TableNames::containerReturnEntries(), [
            'id',
            'container_return_id',
            'container_id',
            'dispatch_entry_id',
            'return_condition',
            'note',
            'cross_return',
        ]))->toBeTrue()
        ->and(Schema::hasIndex(TableNames::containerReturnEntries(), ['dispatch_entry_id']))->toBeTrue()
        ->and(Schema::hasIndex(TableNames::containerReturnEntries(), ['dispatch_entry_id'], 'unique'))->toBeTrue()
        ->and(Schema::hasIndex(TableNames::containerReturnEntries(), ['container_return_id', 'container_id']))->toBeTrue();
});

it('creates a return document with entries and a generated code', function (): void {
    $customer = Customer::query()->create(['name' => 'Foundation Customer']);
    $user = User::query()->create(['name' => 'Preparer', 'email' => 'foundation-preparer@example.com']);
    $container = Container::factory()->create();

    $containerReturn = ContainerReturn::query()->create([
        'customer_id' => $customer->id,
        'user_id' => $user->id,
        'transaction_date' => '2026-07-30',
        'note' => 'Foundation return',
    ]);
    $entry = $containerReturn->entries()->create([
        'container_id' => $container->id,
        'return_condition' => ReturnCondition::Good,
        'note' => 'Inspected',
    ]);

    if (! $entry instanceof ContainerReturnEntry) {
        throw new LogicException('The configured return entry model is invalid.');
    }

    expect($containerReturn->code)->toBe('CRN-2607300001')
        ->and($containerReturn->state)->toBeInstanceOf(ContainerReturnDraftState::class)
        ->and($containerReturn->transaction_date->toDateString())->toBe('2026-07-30')
        ->and($containerReturn->customer->is($customer))->toBeTrue()
        ->and($containerReturn->user->is($user))->toBeTrue()
        ->and($containerReturn->entries)->toHaveCount(1)
        ->and($containerReturn->containers->first()?->is($container))->toBeTrue()
        ->and($entry)->toBeInstanceOf(ContainerReturnEntry::class)
        ->and($entry->return_condition)->toBe(ReturnCondition::Good)
        ->and($entry->cross_return)->toBeFalse()
        ->and($entry->container->is($container))->toBeTrue()
        ->and($container->returnEntries()->sole()->is($entry))->toBeTrue();
});

it('supports only the configured return state transitions', function (): void {
    $containerReturn = ContainerReturn::factory()->create();

    $containerReturn->state->transitionTo(ContainerReturnSubmittedState::class);
    expect($containerReturn->refresh()->state)->toBeInstanceOf(ContainerReturnSubmittedState::class);

    $containerReturn->state->transitionTo(ContainerReturnDraftState::class);
    expect($containerReturn->refresh()->state)->toBeInstanceOf(ContainerReturnDraftState::class);

    expect(fn () => $containerReturn->state->transitionTo(ContainerReturnApprovedState::class))
        ->toThrow(TransitionNotFound::class);

    $containerReturn->state->transitionTo(ContainerReturnSubmittedState::class);
    $containerReturn->state->transitionTo(ContainerReturnApprovedState::class);

    expect($containerReturn->refresh()->state)->toBeInstanceOf(ContainerReturnApprovedState::class)
        ->and(fn () => $containerReturn->state->transitionTo(ContainerReturnDraftState::class))
        ->toThrow(TransitionNotFound::class);
});

it('provides return document and entry factory states', function (): void {
    $submitted = ContainerReturn::factory()->submitted()->create();
    $approved = ContainerReturn::factory()->approved()->create();
    $entry = ContainerReturnEntry::factory()->damaged()->crossReturn()->create();

    expect($submitted->state)->toBeInstanceOf(ContainerReturnSubmittedState::class)
        ->and($approved->state)->toBeInstanceOf(ContainerReturnApprovedState::class)
        ->and($approved->approved_by)->not->toBeNull()
        ->and($approved->approved_at)->not->toBeNull()
        ->and($entry->return_condition)->toBe(ReturnCondition::Damaged)
        ->and($entry->cross_return)->toBeTrue();
});

it('enforces return state controls and maker checker authorization', function (): void {
    $documentPolicy = new ContainerReturnPolicy();
    $entryPolicy = new ContainerReturnEntryPolicy();
    $preparer = new class
    {
        public int $id = 10;

        public function can(string $permission): bool
        {
            return $permission === 'manage.container-returns'
                || $permission === 'manage.container-return-entries';
        }
    };
    $approver = new class
    {
        public int $id = 20;

        public function can(string $permission): bool
        {
            return $permission === 'approve.container-returns';
        }
    };

    $draft = new ContainerReturn(['user_id' => 10, 'state' => 'draft']);
    $submitted = new ContainerReturn(['user_id' => 10, 'state' => 'submitted']);
    $approved = new ContainerReturn(['user_id' => 10, 'state' => 'approved']);
    $draftEntry = new ContainerReturnEntry();
    $draftEntry->setRelation('containerReturn', $draft);
    $approvedEntry = new ContainerReturnEntry();
    $approvedEntry->setRelation('containerReturn', $approved);

    expect($documentPolicy->update($preparer, $draft))->toBeTrue()
        ->and($documentPolicy->update($preparer, $approved))->toBeFalse()
        ->and($documentPolicy->approve($preparer, $submitted))->toBeFalse()
        ->and($documentPolicy->approve($approver, $submitted))->toBeTrue()
        ->and($entryPolicy->update($preparer, $draftEntry))->toBeTrue()
        ->and($entryPolicy->delete($preparer, $approvedEntry))->toBeFalse();
});

it('registers return permissions and filament resources', function (): void {
    Filament::setCurrentPanel('test');
    $resources = Filament::getCurrentPanel()?->getResources() ?? [];

    expect(StorixPermissions::containerReturnPermissions())->toContain(
        'submit.container-returns',
        'approve.container-returns',
        'draft.container-returns',
    )
        ->and(StorixPermissions::containerReturnEntryPermissions())->toContain(
            'create.container-return-entries',
            'update.container-return-entries',
            'delete.container-return-entries',
        )
        ->and($resources)->toContain(ContainerReturnResource::class, ContainerReturnEntryResource::class)
        ->and(ContainerReturnResource::getModel())->toBe(ContainerReturn::class)
        ->and(ContainerReturnEntryResource::getModel())->toBe(ContainerReturnEntry::class);
});
