<?php

declare(strict_types=1);

namespace Storix\Support;

final class TableNames
{
    public static function containers(): string
    {
        return (string) config('storix.containers_table', 'containers');
    }

    public static function dispatches(): string
    {
        return (string) config('storix.dispatches_table', 'dispatches');
    }

    public static function customers(): string
    {
        return (string) config('storix.customer_table', 'customers');
    }

    public static function users(): string
    {
        return (string) config('storix.users_table', 'users');
    }
}
