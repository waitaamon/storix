<?php

declare(strict_types=1);

namespace Storix\Support;

use Illuminate\Support\Facades\Config;

final class TableNames
{
    public static function containers(): string
    {
        return Config::string('storix.tables.containers', 'storix_containers');
    }

    public static function dispatches(): string
    {
        return Config::string('storix.tables.dispatches', 'storix_dispatches');
    }

    public static function dispatchEntries(): string
    {
        return Config::string('storix.tables.dispatch_entries', 'storix_dispatch_entries');
    }

    public static function containerReturns(): string
    {
        return Config::string('storix.tables.container_returns', 'storix_container_returns');
    }

    public static function containerReturnEntries(): string
    {
        return Config::string('storix.tables.container_return_entries', 'storix_container_return_entries');
    }

    public static function containerMovements(): string
    {
        return Config::string('storix.tables.container_movements', 'storix_container_movements');
    }

    public static function customers(): string
    {
        return Config::string('storix.tables.customers', 'customers');
    }

    public static function deliveryNotes(): string
    {
        return Config::string('storix.tables.delivery_notes', 'delivery_notes');
    }

    public static function users(): string
    {
        return Config::string('storix.tables.users', 'users');
    }
}
