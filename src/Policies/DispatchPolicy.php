<?php

declare(strict_types=1);

namespace Storix\Policies;

use Storix\Models\Dispatch;
use Storix\Models\States\DispatchApprovedState;
use Storix\Models\States\DispatchDraftState;

final class DispatchPolicy
{
    public function before(mixed $user): ?bool
    {
        if ($user->can('manage.dispatches')) {
            return true;
        }

        return null;
    }

    public function viewAny(mixed $user): bool
    {
        return $user->can('viewAny.dispatches');
    }

    public function view(mixed $user, Dispatch $dispatch): bool
    {
        return $user->can('view.dispatches');
    }

    public function create(mixed $user): bool
    {
        return $user->can('create.dispatches');
    }

    public function update(mixed $user, Dispatch $dispatch): bool
    {
        return $user->can('update.dispatches');
    }

    public function delete(mixed $user, Dispatch $dispatch): bool
    {
        return $user->can('delete.dispatches');
    }

    public function restore(mixed $user, Dispatch $dispatch): bool
    {
        return $user->can('restore.dispatches');
    }

    public function forceDelete(mixed $user, Dispatch $dispatch): bool
    {
        return $user->can('forceDelete.dispatches');
    }

    public function draft(mixed $user, Dispatch $dispatch): bool
    {
        return $user->can('draft.dispatches') && $dispatch->state->equals(DispatchApprovedState::class);
    }

    public function approve(mixed $user, Dispatch $dispatch): bool
    {
        return $user->can('approve.dispatches') && $dispatch->state->equals(DispatchDraftState::class);
    }

    public function receive(mixed $user): bool
    {
        return $user->can('receive.containers');
    }
}
