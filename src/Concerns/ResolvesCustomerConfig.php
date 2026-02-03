<?php

declare(strict_types=1);

namespace Storix\Concerns;

trait ResolvesCustomerConfig
{
    /**
     * Get the customer columns used for search from config.
     *
     * @return list<string>
     */
    private static function customerSearchColumns(): array
    {
        $columns = config('storix.customer_search_columns', ['name']);

        if (! is_array($columns) || $columns === []) {
            return ['name'];
        }

        return array_values(array_filter(array_map(static fn (mixed $column): string => (string) $column, $columns)));
    }

    /** Get the customer title/display attribute from config. */
    private static function customerTitleAttribute(): string
    {
        return (string) config('storix.customer_title_attribute', 'name');
    }
}
