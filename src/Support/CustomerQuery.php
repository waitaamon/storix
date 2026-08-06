<?php

declare(strict_types=1);

namespace Storix\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use LogicException;
use Storix\Contracts\CustomerQueryModifier;

final class CustomerQuery
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function modify(Builder $query): Builder
    {
        $configuredModifier = Config::get(
            'storix.customer_query_modifier',
            DefaultCustomerQueryModifier::class,
        );

        if ($configuredModifier instanceof Closure) {
            $modifiedQuery = $configuredModifier($query);

            if (! $modifiedQuery instanceof Builder) {
                throw new LogicException(sprintf(
                    'The configured Storix customer query modifier must return [%s], [%s] returned.',
                    Builder::class,
                    get_debug_type($modifiedQuery),
                ));
            }

            return $modifiedQuery;
        }

        if (! is_string($configuredModifier)) {
            throw new LogicException(sprintf(
                'The configured Storix customer query modifier must be a class name or closure, [%s] given.',
                get_debug_type($configuredModifier),
            ));
        }

        $modifierClass = $configuredModifier;
        $modifier = app($modifierClass);

        if (! $modifier instanceof CustomerQueryModifier) {
            throw new LogicException(sprintf(
                'The configured Storix customer query modifier [%s] must implement [%s].',
                $modifierClass,
                CustomerQueryModifier::class,
            ));
        }

        return $modifier($query);
    }
}
