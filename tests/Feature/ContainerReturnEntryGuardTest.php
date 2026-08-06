<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Storix\Actions\AddContainerReturnEntryAction;
use Storix\Actions\UpdateContainerReturnEntryAction;
use Storix\Data\AddContainerReturnEntryData;
use Storix\Enums\ReturnCondition;
use Storix\Models\Container;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\DispatchEntry;

it('normalizes an explicitly empty return condition to good', function (): void {
    $data = new AddContainerReturnEntryData(containerId: 1, condition: null);

    expect($data->returnCondition())->toBe(ReturnCondition::Good);
});

it('rejects duplicate containers within one return document', function (): void {
    $containerReturn = ContainerReturn::factory()->draft()->create();
    $container = Container::factory()->create();
    $data = new AddContainerReturnEntryData(
        containerId: $container->id,
        condition: ReturnCondition::Good,
    );

    app(AddContainerReturnEntryAction::class)->handle($containerReturn, $data);

    expect(fn () => app(AddContainerReturnEntryAction::class)->handle($containerReturn, $data))
        ->toThrow(
            DomainException::class,
            "Container [{$container->serial}] has already been added to this container return.",
        )
        ->and($containerReturn->entries()->count())->toBe(1);
});

it('allows an entry to retain its container but rejects changing it to a document duplicate', function (): void {
    $containerReturn = ContainerReturn::factory()->draft()->create();
    $firstContainer = Container::factory()->create();
    $secondContainer = Container::factory()->create();
    $firstEntry = app(AddContainerReturnEntryAction::class)->handle(
        $containerReturn,
        new AddContainerReturnEntryData(containerId: $firstContainer->id),
    );
    $secondEntry = app(AddContainerReturnEntryAction::class)->handle(
        $containerReturn,
        new AddContainerReturnEntryData(containerId: $secondContainer->id),
    );

    $updated = app(UpdateContainerReturnEntryAction::class)->handle(
        $firstEntry,
        new AddContainerReturnEntryData(
            containerId: $firstContainer->id,
            condition: ReturnCondition::Damaged,
        ),
    );

    expect($updated->return_condition)->toBe(ReturnCondition::Damaged)
        ->and(fn () => app(UpdateContainerReturnEntryAction::class)->handle(
            $secondEntry,
            new AddContainerReturnEntryData(containerId: $firstContainer->id),
        ))->toThrow(
            DomainException::class,
            "Container [{$firstContainer->serial}] has already been added to this container return.",
        )
        ->and($secondEntry->refresh()->container_id)->toBe($secondContainer->id);
});

it('enforces one posted return reconciliation per dispatch entry', function (): void {
    $dispatchEntry = DispatchEntry::factory()->create();

    ContainerReturnEntry::factory()->create([
        'dispatch_entry_id' => $dispatchEntry->id,
    ]);

    expect(fn () => ContainerReturnEntry::factory()->create([
        'dispatch_entry_id' => $dispatchEntry->id,
    ]))->toThrow(QueryException::class);
});
