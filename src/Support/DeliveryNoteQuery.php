<?php

declare(strict_types=1);

namespace Storix\Support;

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
        $modifierClass = Config::string(
            'storix.delivery_note_query_modifier',
            DefaultDeliveryNoteQueryModifier::class,
        );
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
