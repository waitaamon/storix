<?php

declare(strict_types=1);

namespace Storix\Policies;

use Storix\Models\ContainerReturnEntry;
use Storix\Models\States\ContainerReturnDraftState;

final class ContainerReturnEntryPolicy
{
    public function viewAny(mixed $user): bool
    {
        return $this->allowed($user, 'viewAny.container-return-entries');
    }

    public function view(mixed $user): bool
    {
        return $this->allowed($user, 'view.container-return-entries');
    }

    public function create(mixed $user): bool
    {
        return $this->allowed($user, 'create.container-return-entries');
    }

    public function update(mixed $user, ContainerReturnEntry $entry): bool
    {
        return $this->allowed($user, 'update.container-return-entries')
            && $entry->containerReturn->state->equals(ContainerReturnDraftState::class);
    }

    public function delete(mixed $user, ContainerReturnEntry $entry): bool
    {
        return $this->allowed($user, 'delete.container-return-entries')
            && $entry->containerReturn->state->equals(ContainerReturnDraftState::class);
    }

    private function allowed(mixed $user, string $permission): bool
    {
        if ($user->can('manage.container-return-entries')) {
            return true;
        }

        return (bool) $user->can($permission);
    }
}
