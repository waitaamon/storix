<?php

declare(strict_types=1);

namespace Storix\Services;

use Illuminate\Database\Eloquent\Builder;
use Storix\Exceptions\StorixException;
use Storix\Models\Container;
use Storix\Models\ContainerDispatchItem;

final class StorixValidator
{
    /**
     * Find a container by its serial or throw.
     *
     * @throws StorixException
     */
    public function resolveContainerBySerial(string $serial): Container
    {
        $container = Container::query()
            ->where('serial', mb_trim($serial))
            ->first();

        if (! $container instanceof Container) {
            throw new StorixException(sprintf('Container with serial [%s] was not found.', $serial));
        }

        return $container;
    }

    /**
     * Assert that a container is active and has no open dispatch.
     *
     * @throws StorixException
     */
    public function assertDispatchable(Container $container): void
    {
        if (! $container->is_active) {
            throw new StorixException(sprintf('Container [%s] is inactive and cannot be dispatched.', $container->serial));
        }

        if ($this->hasOpenDispatch($container)) {
            throw new StorixException(sprintf('Container [%s] is already dispatched and not yet returned.', $container->serial));
        }
    }

    /**
     * Find the most recent unreturned dispatch item for a container, optionally scoped to a customer.
     *
     * @throws StorixException
     */
    public function resolveOpenDispatchItem(Container $container, ?int $customerId = null): ContainerDispatchItem
    {
        $query = ContainerDispatchItem::query()
            ->where('container_id', $container->getKey())
            ->whereDoesntHave('returnItem')
            ->whereHas('dispatch', static fn (Builder $dispatchQuery): Builder => $customerId === null
                ? $dispatchQuery
                : $dispatchQuery->where('customer_id', $customerId))
            ->with('dispatch')
            ->latest('id');

        $openDispatchItem = $query->first();

        if (! $openDispatchItem instanceof ContainerDispatchItem) {
            throw new StorixException(sprintf('Container [%s] has no open dispatch record to return.', $container->serial));
        }

        return $openDispatchItem;
    }

    /** Check whether a container has any unreturned dispatch items. */
    public function hasOpenDispatch(Container $container): bool
    {
        return ContainerDispatchItem::query()
            ->where('container_id', $container->getKey())
            ->whereDoesntHave('returnItem')
            ->exists();
    }
}
