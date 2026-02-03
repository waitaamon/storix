<?php

declare(strict_types=1);

namespace Storix\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Storix\StorixServiceProvider;
use Storix\Tests\Fixtures\Models\Customer;
use Storix\Tests\Fixtures\Models\User;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            StorixServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('storix.customer_model', Customer::class);
        $app['config']->set('storix.user_model', User::class);
        $app['config']->set('storix.customer_table', 'customers');
        $app['config']->set('storix.user_table', 'users');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('customers', static function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
