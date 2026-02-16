<?php

declare(strict_types=1);

namespace WaitAmon\Storix\Policies;

use WaitAmon\Storix\Models\Dispatch;

final class DispatchPolicy
{
    public function viewAny(mixed $user): bool
    {
        return $this->allowed($user, 'viewAny.dispatches');
    }

    public function view(mixed $user, Dispatch $dispatch): bool
    {
        return $this->allowed($user, 'view.dispatches');
    }

    public function create(mixed $user): bool
    {
        return $this->allowed($user, 'create.dispatches');
    }

    public function update(mixed $user, Dispatch $dispatch): bool
    {
        return $this->allowed($user, 'update.dispatches');
    }

    public function delete(mixed $user, Dispatch $dispatch): bool
    {
        return $this->allowed($user, 'delete.dispatches');
    }

    public function restore(mixed $user, Dispatch $dispatch): bool
    {
        return $this->allowed($user, 'restore.dispatches');
    }

    public function forceDelete(mixed $user, Dispatch $dispatch): bool
    {
        return $this->allowed($user, 'forceDelete.dispatches');
    }

    public function receiveContainers(mixed $user): bool
    {
        return $this->allowed($user, 'receive.containers');
    }

    private function allowed(mixed $user, string $permission): bool
    {
        return is_object($user) && method_exists($user, 'can') && $user->can($permission);
    }
}
