<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\Services;

use Illuminate\Database\Eloquent\Builder;
use Storix\ContainerMovement\Exceptions\ContainerMovementException;
use Storix\ContainerMovement\Models\Container;
use Storix\ContainerMovement\Models\ContainerDispatchItem;

final class ContainerMovementValidator
{
    public function resolveContainerBySerial(string $serial): Container
    {
        $container = Container::query()
            ->where('serial', trim($serial))
            ->first();

        if (! $container instanceof Container) {
            throw new ContainerMovementException(sprintf('Container with serial [%s] was not found.', $serial));
        }

        return $container;
    }

    public function assertDispatchable(Container $container): void
    {
        if (! $container->is_active) {
            throw new ContainerMovementException(sprintf('Container [%s] is inactive and cannot be dispatched.', $container->serial));
        }

        if ($this->hasOpenDispatch($container)) {
            throw new ContainerMovementException(sprintf('Container [%s] is already dispatched and not yet returned.', $container->serial));
        }
    }

    public function resolveOpenDispatchItem(Container $container, ?int $customerId = null): ContainerDispatchItem
    {
        $query = ContainerDispatchItem::query()
            ->where('container_id', $container->getKey())
            ->whereDoesntHave('returnItem')
            ->whereHas('dispatch', static function (Builder $dispatchQuery) use ($customerId): Builder {
                return $customerId === null
                    ? $dispatchQuery
                    : $dispatchQuery->where('customer_id', $customerId);
            })
            ->with('dispatch')
            ->latest('id');

        $openDispatchItem = $query->first();

        if (! $openDispatchItem instanceof ContainerDispatchItem) {
            throw new ContainerMovementException(sprintf('Container [%s] has no open dispatch record to return.', $container->serial));
        }

        return $openDispatchItem;
    }

    public function hasOpenDispatch(Container $container): bool
    {
        return ContainerDispatchItem::query()
            ->where('container_id', $container->getKey())
            ->whereDoesntHave('returnItem')
            ->exists();
    }
}
