<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Storix\Models\Container;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Policies\ContainerPolicy;
use Storix\Policies\DispatchEntryPolicy;
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
    expect($policy->delete($user))->toBeFalse();
});

it('enforces dispatch permissions including receive containers', function (): void {
    $policy = new DispatchPolicy();

    $user = new class
    {
        /**
         * @var list<string>
         */
        private array $permissions = ['viewAny.dispatches', 'approve.dispatches', 'void.dispatches'];

        public function can(string $permission): bool
        {
            return in_array($permission, $this->permissions, true);
        }
    };

    expect($policy->viewAny($user))->toBeTrue();
    expect($policy->approve($user, new Dispatch(['state' => 'draft'])))->toBeTrue();
    expect($policy->void($user, new Dispatch(['state' => 'approved'])))->toBeTrue();
    expect($policy->forceDelete($user, new Dispatch()))->toBeFalse();
});

it('enforces dispatch entry receive permissions', function (): void {
    $policy = new DispatchEntryPolicy();

    $user = new class
    {
        /**
         * @var list<string>
         */
        private array $permissions = ['receive.dispatch-entries'];

        public function can(string $permission): bool
        {
            return in_array($permission, $this->permissions, true);
        }
    };

    expect($policy->receive($user, new DispatchEntry()))->toBeTrue();
});

it('registers storix policies and morph aliases in the service provider', function (): void {
    expect(Gate::getPolicyFor(Container::class))->toBeInstanceOf(ContainerPolicy::class)
        ->and(Gate::getPolicyFor(Dispatch::class))->toBeInstanceOf(DispatchPolicy::class)
        ->and(Gate::getPolicyFor(DispatchEntry::class))->toBeInstanceOf(DispatchEntryPolicy::class)
        ->and(Relation::getMorphedModel('storix_container'))->toBe(Container::class)
        ->and(Relation::getMorphedModel('storix_dispatch'))->toBe(Dispatch::class)
        ->and(Relation::getMorphedModel('storix_dispatch_entry'))->toBe(DispatchEntry::class);
});
