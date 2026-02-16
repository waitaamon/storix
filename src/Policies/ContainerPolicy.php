<?php

declare(strict_types=1);

namespace WaitAmon\Storix\Policies;

use WaitAmon\Storix\Models\Container;

final class ContainerPolicy
{
    public function viewAny(mixed $user): bool
    {
        return $this->allowed($user, 'viewAny.containers');
    }

    public function view(mixed $user, Container $container): bool
    {
        return $this->allowed($user, 'view.containers');
    }

    public function create(mixed $user): bool
    {
        return $this->allowed($user, 'create.containers');
    }

    public function update(mixed $user, Container $container): bool
    {
        return $this->allowed($user, 'update.containers');
    }

    public function delete(mixed $user, Container $container): bool
    {
        return $this->allowed($user, 'delete.containers');
    }

    public function restore(mixed $user, Container $container): bool
    {
        return $this->allowed($user, 'restore.containers');
    }

    public function forceDelete(mixed $user, Container $container): bool
    {
        return $this->allowed($user, 'forceDelete.containers');
    }

    private function allowed(mixed $user, string $permission): bool
    {
        return is_object($user) && method_exists($user, 'can') && $user->can($permission);
    }
}
