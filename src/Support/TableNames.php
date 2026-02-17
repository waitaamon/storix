<?php

declare(strict_types=1);

namespace Storix\Support;

use Illuminate\Support\Facades\Config;

final class TableNames
{
    public static function containers(): string
    {
        return Config::string('storix.tables.containers', 'containers');
    }

    public static function dispatches(): string
    {
        return Config::string('storix.tables.dispatches', 'dispatches');
    }

    public static function dispatchEntries(): string
    {
        return Config::string('storix.tables.dispatch_entries', 'dispatch_entries');
    }

    public static function customers(): string
    {
        return Config::string('storix.tables.customers', 'customers');
    }

    public static function users(): string
    {
        return Config::string('storix.tables.users', 'users');
    }
}
