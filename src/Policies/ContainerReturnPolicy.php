<?php

declare(strict_types=1);

namespace Storix\Policies;

use Storix\Models\ContainerReturn;
use Storix\Models\States\ContainerReturnDraftState;
use Storix\Models\States\ContainerReturnSubmittedState;

final class ContainerReturnPolicy
{
    public function viewAny(mixed $user): bool
    {
        return $this->allowed($user, 'viewAny.container-returns');
    }

    public function view(mixed $user): bool
    {
        return $this->allowed($user, 'view.container-returns');
    }

    public function create(mixed $user): bool
    {
        return $this->allowed($user, 'create.container-returns');
    }

    public function update(mixed $user, ContainerReturn $containerReturn): bool
    {
        return $this->allowed($user, 'update.container-returns')
            && $containerReturn->state->equals(ContainerReturnDraftState::class);
    }

    public function delete(mixed $user, ContainerReturn $containerReturn): bool
    {
        return $this->allowed($user, 'delete.container-returns')
            && $containerReturn->state->equals(ContainerReturnDraftState::class);
    }

    public function restore(mixed $user, ContainerReturn $containerReturn): bool
    {
        return $this->allowed($user, 'restore.container-returns')
            && $containerReturn->state->equals(ContainerReturnDraftState::class);
    }

    public function forceDelete(mixed $user, ContainerReturn $containerReturn): bool
    {
        return $this->allowed($user, 'forceDelete.container-returns')
            && $containerReturn->state->equals(ContainerReturnDraftState::class);
    }

    public function submit(mixed $user, ContainerReturn $containerReturn): bool
    {
        return $this->allowed($user, 'submit.container-returns')
            && $containerReturn->state->equals(ContainerReturnDraftState::class);
    }

    public function approve(mixed $user, ContainerReturn $containerReturn): bool
    {
        return $this->allowed($user, 'approve.container-returns')
            && $containerReturn->state->equals(ContainerReturnSubmittedState::class)
            && (string) $this->userIdentifier($user) !== (string) $containerReturn->user_id;
    }

    public function returnToDraft(mixed $user, ContainerReturn $containerReturn): bool
    {
        return $this->allowed($user, 'returnToDraft.container-returns')
            && $containerReturn->state->equals(ContainerReturnSubmittedState::class);
    }

    private function allowed(mixed $user, string $permission): bool
    {
        if ($user->can('manage.container-returns')) {
            return true;
        }

        return (bool) $user->can($permission);
    }

    private function userIdentifier(mixed $user): mixed
    {
        if (is_object($user) && method_exists($user, 'getAuthIdentifier')) {
            return $user->getAuthIdentifier();
        }

        return is_object($user) ? ($user->id ?? null) : null;
    }
}
