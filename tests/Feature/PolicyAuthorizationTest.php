<?php

declare(strict_types=1);

use Storix\Models\Container;
use Storix\Models\Dispatch;
use Storix\Policies\ContainerPolicy;
use Storix\Policies\DispatchPolicy;

it('enforces container permissions', function (): void {
    $policy = new ContainerPolicy();

    $user = new class
    {
        /**
         * @var list<string>
         */
        private array $permissions = ['viewAny.containers', 'create.containers'];

        public function can(string $permission): bool
        {
            return in_array($permission, $this->permissions, true);
        }
    };

    expect($policy->viewAny($user))->toBeTrue();
    expect($policy->create($user))->toBeTrue();
    expect($policy->delete($user, new Container()))->toBeFalse();
});

it('enforces dispatch permissions including receive containers', function (): void {
    $policy = new DispatchPolicy();

    $user = new class
    {
        /**
         * @var list<string>
         */
        private array $permissions = ['viewAny.dispatches', 'receive.containers'];

        public function can(string $permission): bool
        {
            return in_array($permission, $this->permissions, true);
        }
    };

    expect($policy->viewAny($user))->toBeTrue();
    expect($policy->receive($user))->toBeTrue();
    expect($policy->forceDelete($user, new Dispatch()))->toBeFalse();
});
