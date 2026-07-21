<?php

declare(strict_types=1);

namespace Storix\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use LogicException;
use Storix\Contracts\DeliveryNoteQueryModifier;

final class DeliveryNoteQuery
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
            'storix.delivery_note_query_modifier',
            DefaultDeliveryNoteQueryModifier::class,
        );

        // Support configuration files published before modifiers became
        // container-resolved classes. New configuration should use a class
        // string so that Laravel can cache it.
        if ($configuredModifier instanceof Closure) {
            $modifiedQuery = $configuredModifier($query);

            if (! $modifiedQuery instanceof Builder) {
                throw new LogicException(sprintf(
                    'The configured Storix delivery note query modifier must return [%s], [%s] returned.',
                    Builder::class,
                    get_debug_type($modifiedQuery),
                ));
            }

            return $modifiedQuery;
        }

        if (! is_string($configuredModifier)) {
            throw new LogicException(sprintf(
                'The configured Storix delivery note query modifier must be a class name or closure, [%s] given.',
                get_debug_type($configuredModifier),
            ));
        }

        $modifierClass = $configuredModifier;
        $modifier = app($modifierClass);

        if (! $modifier instanceof DeliveryNoteQueryModifier) {
            throw new LogicException(sprintf(
                'The configured Storix delivery note query modifier [%s] must implement [%s].',
                $modifierClass,
                DeliveryNoteQueryModifier::class,
            ));
        }

        return $modifier($query);
    }
}
