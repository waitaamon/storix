<?php

declare(strict_types=1);

namespace Storix\Policies;

use Storix\Models\Container;

final class ContainerPolicy
{
    public function before(mixed $user): ?bool
    {
        if ($user->can('manage.containers')) {
            return true;
        }

        return null;
    }
    public function viewAny(mixed $user): bool
    {
        return $user->can('viewAny.containers');
    }

    public function view(mixed $user, Container $container): bool
    {
        return $user->can('view.containers');
    }

    public function create(mixed $user): bool
    {
        return $user->can('create.containers');
    }

    public function update(mixed $user, Container $container): bool
    {
        return $user->can('update.containers');
    }

    public function delete(mixed $user, Container $container): bool
    {
        return $user->can('delete.containers');
    }

    public function restore(mixed $user, Container $container): bool
    {
        return $user->can('restore.containers');
    }

    public function forceDelete(mixed $user, Container $container): bool
    {
        return $user->can('forceDelete.containers');
    }
}
