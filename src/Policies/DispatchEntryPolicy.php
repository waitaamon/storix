<?php

declare(strict_types=1);

namespace Storix\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Storix\Models\DispatchEntry;

final class DispatchEntryPolicy
{
    use HandlesAuthorization;

    public function before(mixed $user): ?bool
    {
        if ($user->can('manage.dispatch-entries')) {
            return true;
        }

        return null;
    }

    public function viewAny(mixed $user): bool
    {
        return $user->can('viewAny.dispatch-entries');
    }

    public function view(mixed $user, DispatchEntry $dispatchEntry): bool
    {
        return $user->can('view.dispatch-entries');
    }

    public function create(mixed $user): bool
    {
        return $user->can('create.dispatch-entries');
    }

    public function update(mixed $user, DispatchEntry $dispatchEntry): bool
    {
        return $user->can('update.dispatch-entries');
    }

    public function delete(mixed $user, DispatchEntry $dispatchEntry): bool
    {
        return $user->can('delete.dispatch-entries');
    }

    public function restore(mixed $user, DispatchEntry $dispatchEntry): bool
    {
        return $user->can('restore.dispatch-entries');
    }

    public function forceDelete(mixed $user, DispatchEntry $dispatchEntry): bool
    {
        return $user->can('forceDelete.dispatch-entries');
    }
}
