<?php

declare(strict_types=1);

namespace Storix\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

interface CustomerQueryModifier
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function __invoke(Builder $query): Builder;
}
