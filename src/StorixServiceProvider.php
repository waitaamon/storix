<?php

declare(strict_types=1);

namespace Storix;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Storix\Commands\SyncPermissionsCommand;
use Storix\Events\GenerateDraftDispatchRequested;
use Storix\Listeners\GenerateDraftDispatch;
use Storix\Models\Container;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Policies\ContainerPolicy;
use Storix\Policies\DispatchEntryPolicy;
use Storix\Policies\DispatchPolicy;

final class StorixServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('storix')
            ->hasConfigFile()
            ->hasCommand(SyncPermissionsCommand::class)
            ->discoversMigrations()
            ->runsMigrations();
    }

    public function packageBooted(): void
    {
        Event::listen(GenerateDraftDispatchRequested::class, GenerateDraftDispatch::class);

        $containerModel = Config::string('storix.models.container', Container::class);
        $dispatchModel = Config::string('storix.models.dispatch', Dispatch::class);
        $dispatchEntryModel = Config::string('storix.models.dispatch_entry', DispatchEntry::class);

        $containerModel = is_a($containerModel, Model::class, true) ? $containerModel : Container::class;
        $dispatchModel = is_a($dispatchModel, Model::class, true) ? $dispatchModel : Dispatch::class;
        $dispatchEntryModel = is_a($dispatchEntryModel, Model::class, true) ? $dispatchEntryModel : DispatchEntry::class;

        Gate::policy($containerModel, ContainerPolicy::class);
        Gate::policy($dispatchModel, DispatchPolicy::class);
        Gate::policy($dispatchEntryModel, DispatchEntryPolicy::class);

        Relation::morphMap([
            'storix_container' => $containerModel,
            'storix_dispatch' => $dispatchModel,
            'storix_dispatch_entry' => $dispatchEntryModel,
        ]);
    }
}
