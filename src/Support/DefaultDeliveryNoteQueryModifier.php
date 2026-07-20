<?php

declare(strict_types=1);

namespace Storix\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Storix\Contracts\DeliveryNoteQueryModifier;

final class DefaultDeliveryNoteQueryModifier implements DeliveryNoteQueryModifier
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function __invoke(Builder $query): Builder
    {
        $year = FinancialYear::selected();

        $query
            ->whereNull('dispatched_at')
            ->where('state', 'approved');

        if ($year) {
            $query->whereBetween('transaction_date', [
                $year->start_date,
                $year->end_date,
            ]);
        }

        return $query;
    }
}
