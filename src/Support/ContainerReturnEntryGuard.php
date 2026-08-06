<?php

declare(strict_types=1);

namespace Storix\Support;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Storix\Models\Container;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\States\ContainerReturnDraftState;

final class ContainerReturnEntryGuard
{
    public function ensureCanStore(
        ContainerReturn $containerReturn,
        Container $container,
        ?ContainerReturnEntry $except = null,
    ): void {
        if (! $containerReturn->state->equals(ContainerReturnDraftState::class)) {
            throw new DomainException('Entries can only be changed on draft container returns.');
        }

        if (! $container->is_active) {
            throw new DomainException("Container [{$container->serial}] is inactive.");
        }

        $exceptKey = $except?->getKey();

        $duplicateExists = ContainerReturnEntry::query()
            ->where('container_return_id', $containerReturn->getKey())
            ->where('container_id', $container->getKey())
            ->when(
                $exceptKey !== null,
                fn (Builder $query): Builder => $query->whereKeyNot($exceptKey),
            )
            ->exists();

        if ($duplicateExists) {
            throw new DomainException(
                "Container [{$container->serial}] has already been added to this container return.",
            );
        }
    }
}
