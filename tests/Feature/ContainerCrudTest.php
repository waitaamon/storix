<?php

declare(strict_types=1);

use Storix\Models\Container;

it('creates and updates a container', function (): void {
    $container = Container::query()->create([
        'name' => 'BIN-A1',
        'serial' => 'STRX-0001',
        'is_active' => true,
    ]);

    expect($container->exists)->toBeTrue();

    $container->update([
        'description' => 'Primary reusable vessel',
        'is_active' => false,
    ]);

    expect($container->refresh()->description)->toBe('Primary reusable vessel');
    expect($container->is_active)->toBeFalse();
});

it('supports container soft delete and restore', function (): void {
    $container = Container::factory()->create();

    $container->delete();

    expect(Container::query()->find($container->id))->toBeNull();
    expect(Container::withTrashed()->find($container->id))->not->toBeNull();

    $container->restore();

    expect(Container::query()->find($container->id))->not->toBeNull();
});
