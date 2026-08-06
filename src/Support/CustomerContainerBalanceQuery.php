<?php

declare(strict_types=1);

namespace Storix\Support;

use Illuminate\Database\Eloquent\Builder;
use Storix\Data\CustomerContainerBalanceData;
use Storix\Enums\ReturnCondition;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\DispatchEntry;
use Storix\Models\States\ContainerReturnApprovedState;
use Storix\Models\States\DispatchApprovedState;

final class CustomerContainerBalanceQuery
{
    public function forCustomer(int|string $customerId): CustomerContainerBalanceData
    {
        $dispatched = DispatchEntry::query()
            ->whereHas(
                'dispatch',
                fn (Builder $query): Builder => $query
                    ->where('customer_id', $customerId)
                    ->whereState('state', DispatchApprovedState::class),
            )
            ->count();

        $returnCounts = ContainerReturnEntry::query()
            ->whereHas(
                'containerReturn',
                fn (Builder $query): Builder => $query
                    ->where('customer_id', $customerId)
                    ->whereState('state', ContainerReturnApprovedState::class),
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN return_condition IN (?, ?) THEN 1 ELSE 0 END), 0) AS returned_count',
                [ReturnCondition::Good->value, ReturnCondition::Damaged->value],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN return_condition = ? THEN 1 ELSE 0 END), 0) AS lost_count',
                [ReturnCondition::Lost->value],
            )
            ->first();

        $returned = (int) $returnCounts?->getAttribute('returned_count');
        $lost = (int) $returnCounts?->getAttribute('lost_count');

        return new CustomerContainerBalanceData(
            dispatched: $dispatched,
            returned: $returned,
            lost: $lost,
            outstanding: $dispatched - $returned - $lost,
        );
    }
}
