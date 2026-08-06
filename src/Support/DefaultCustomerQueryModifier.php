<?php

declare(strict_types=1);

namespace Storix\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Storix\Contracts\CustomerQueryModifier;

final class DefaultCustomerQueryModifier implements CustomerQueryModifier
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function __invoke(Builder $query): Builder
    {
        return $query->whereRelation('category', 'slug', 'accounts-receivable');
    }
}
