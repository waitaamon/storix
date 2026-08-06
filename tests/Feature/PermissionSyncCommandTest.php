<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Storix\Permissions\StorixPermissions;
use Storix\StorixServiceProvider;

it('does not access the database while the service provider boots', function (): void {
    DB::flushQueryLog();
    DB::enableQueryLog();

    new StorixServiceProvider(app())->packageBooted();

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBe([]);
});

it('syncs all permissions after migrations and is idempotent', function (): void {
    Schema::create('roles', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
        $table->unique(['name', 'guard_name']);
    });

    Schema::create('role_has_permissions', function (Blueprint $table): void {
        $table->unsignedBigInteger('permission_id');
        $table->unsignedBigInteger('role_id');
        $table->primary(['permission_id', 'role_id']);
    });

    $exitCode = Artisan::call('storix:sync-permissions');

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and(Artisan::output())->toContain('Synced 43 Storix permissions for the [web] guard.')
        ->and(Artisan::call('storix:sync-permissions'))->toBe(Command::SUCCESS);

    expect(Permission::query()->orderBy('id')->pluck('name')->all())
        ->toBe(StorixPermissions::all());
});

it('fails clearly when permission migrations have not run', function (): void {
    Schema::drop('permissions');

    $exitCode = Artisan::call('storix:sync-permissions');

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('The permissions table does not exist. Run the application migrations before syncing Storix permissions.');
});
