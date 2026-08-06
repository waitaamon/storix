<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Storix\Contracts\CustomerQueryModifier;
use Storix\Contracts\DeliveryNoteQueryModifier;
use Storix\Support\CustomerQuery;
use Storix\Support\DefaultCustomerQueryModifier;
use Storix\Support\DefaultDeliveryNoteQueryModifier;
use Storix\Support\DeliveryNoteQuery;
use Storix\Tests\Fixtures\Models\Customer;
use Storix\Tests\Fixtures\Models\DeliveryNote;

final class TestCustomerQueryModifier implements CustomerQueryModifier
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function __invoke(Builder $query): Builder
    {
        return $query->where('name', 'custom');
    }
}

final class TestDeliveryNoteQueryModifier implements DeliveryNoteQueryModifier
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function __invoke(Builder $query): Builder
    {
        return $query->where('name', 'custom');
    }
}

it('provides configuration that can be exported for Laravel config caching', function (): void {
    $configuration = config('storix');
    $exported = var_export($configuration, true);

    expect($exported)->not->toContain('Closure::__set_state')
        ->and(eval("return {$exported};"))->toBe($configuration)
        ->and(config('storix.customer_query_modifier'))->toBe(DefaultCustomerQueryModifier::class)
        ->and(config('storix.delivery_note_query_modifier'))->toBe(DefaultDeliveryNoteQueryModifier::class);
});

it('resolves the configured customer query modifier from the container', function (): void {
    Config::set('storix.customer_query_modifier', TestCustomerQueryModifier::class);

    $query = CustomerQuery::modify(Customer::query());

    expect($query->toSql())->toContain('"name" = ?')
        ->and($query->getBindings())->toBe(['custom']);
});

it('supports legacy closure customer query modifiers', function (): void {
    Config::set(
        'storix.customer_query_modifier',
        static fn (Builder $query): Builder => $query->where('name', 'legacy'),
    );

    $query = CustomerQuery::modify(Customer::query());

    expect($query->toSql())->toContain('"name" = ?')
        ->and($query->getBindings())->toBe(['legacy']);
});

it('resolves the configured delivery note query modifier from the container', function (): void {
    Config::set('storix.delivery_note_query_modifier', TestDeliveryNoteQueryModifier::class);

    $query = DeliveryNoteQuery::modify(DeliveryNote::query());

    expect($query->toSql())->toContain('"name" = ?')
        ->and($query->getBindings())->toBe(['custom']);
});

it('supports legacy closure delivery note query modifiers', function (): void {
    Config::set(
        'storix.delivery_note_query_modifier',
        static fn (Builder $query): Builder => $query->where('name', 'legacy'),
    );

    $query = DeliveryNoteQuery::modify(DeliveryNote::query());

    expect($query->toSql())->toContain('"name" = ?')
        ->and($query->getBindings())->toBe(['legacy']);
});
