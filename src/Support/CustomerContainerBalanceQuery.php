<?php

declare(strict_types=1);

namespace Storix\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Config;
use LogicException;
use Storix\Data\CustomerContainerBalanceData;
use Storix\Enums\ReturnCondition;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Models\States\ContainerReturnApprovedState;
use Storix\Models\States\DispatchApprovedState;

final class CustomerContainerBalanceQuery
{
    private const string DISPATCH_AGGREGATE_ALIAS = 'storix_balance_dispatch_totals';

    private const string RETURN_AGGREGATE_ALIAS = 'storix_balance_return_totals';

    public function forCustomer(int|string $customerId): CustomerContainerBalanceData
    {
        $customer = $this->withAggregates($this->customerModel()::query())
            ->whereKey($customerId)
            ->first();

        $dispatched = (int) $customer?->getAttribute('dispatched');
        $returned = (int) $customer?->getAttribute('returned');
        $lost = (int) $customer?->getAttribute('lost');

        return new CustomerContainerBalanceData(
            dispatched: $dispatched,
            returned: $returned,
            lost: $lost,
            outstanding: $dispatched - $returned - $lost,
        );
    }

    /**
     * @return Builder<Model>
     */
    public function forReport(): Builder
    {
        $query = CustomerQuery::modify($this->customerModel()::query());

        return $this->withAggregates($query)
            ->where(function (Builder $query): void {
                $query
                    ->whereNotNull(self::DISPATCH_AGGREGATE_ALIAS.'.customer_id')
                    ->orWhereNotNull(self::RETURN_AGGREGATE_ALIAS.'.customer_id');
            });
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private function withAggregates(Builder $query): Builder
    {
        $customer = $query->getModel();
        $customerKey = $customer->qualifyColumn($customer->getKeyName());
        $customerTable = $customer->getTable();

        return $query
            ->select("{$customerTable}.*")
            ->leftJoinSub(
                $this->dispatchAggregate($customer),
                self::DISPATCH_AGGREGATE_ALIAS,
                self::DISPATCH_AGGREGATE_ALIAS.'.customer_id',
                '=',
                $customerKey,
            )
            ->leftJoinSub(
                $this->returnAggregate($customer),
                self::RETURN_AGGREGATE_ALIAS,
                self::RETURN_AGGREGATE_ALIAS.'.customer_id',
                '=',
                $customerKey,
            )
            ->selectRaw('COALESCE('.self::DISPATCH_AGGREGATE_ALIAS.'.dispatched, 0) AS dispatched')
            ->selectRaw('COALESCE('.self::RETURN_AGGREGATE_ALIAS.'.returned, 0) AS returned')
            ->selectRaw('COALESCE('.self::RETURN_AGGREGATE_ALIAS.'.lost, 0) AS lost')
            ->selectRaw(
                'COALESCE('.self::DISPATCH_AGGREGATE_ALIAS.'.dispatched, 0)'
                .' - COALESCE('.self::RETURN_AGGREGATE_ALIAS.'.returned, 0)'
                .' - COALESCE('.self::RETURN_AGGREGATE_ALIAS.'.lost, 0) AS balance',
            );
    }

    private function dispatchAggregate(Model $customer): QueryBuilder
    {
        $dispatch = $this->model('dispatch', Dispatch::class);
        $dispatchEntry = $this->model('dispatch_entry', DispatchEntry::class);
        $dispatchTable = $dispatch->getTable();
        $dispatchEntryTable = $dispatchEntry->getTable();
        $dispatchAlias = 'storix_balance_dispatches';
        $entryAlias = 'storix_balance_dispatch_entries';

        return $customer->getConnection()->query()
            ->from("{$dispatchEntryTable} as {$entryAlias}")
            ->join(
                "{$dispatchTable} as {$dispatchAlias}",
                "{$dispatchAlias}.{$dispatch->getKeyName()}",
                '=',
                "{$entryAlias}.dispatch_id",
            )
            ->where("{$dispatchAlias}.state", DispatchApprovedState::$name)
            ->whereNull("{$dispatchAlias}.deleted_at")
            ->whereNull("{$entryAlias}.deleted_at")
            ->select("{$dispatchAlias}.customer_id")
            ->selectRaw('COUNT(*) AS dispatched')
            ->groupBy("{$dispatchAlias}.customer_id");
    }

    private function returnAggregate(Model $customer): QueryBuilder
    {
        $containerReturn = $this->model('container_return', ContainerReturn::class);
        $returnEntry = $this->model('container_return_entry', ContainerReturnEntry::class);
        $returnTable = $containerReturn->getTable();
        $returnEntryTable = $returnEntry->getTable();
        $returnAlias = 'storix_balance_returns';
        $entryAlias = 'storix_balance_return_entries';

        return $customer->getConnection()->query()
            ->from("{$returnEntryTable} as {$entryAlias}")
            ->join(
                "{$returnTable} as {$returnAlias}",
                "{$returnAlias}.{$containerReturn->getKeyName()}",
                '=',
                "{$entryAlias}.container_return_id",
            )
            ->where("{$returnAlias}.state", ContainerReturnApprovedState::$name)
            ->whereNull("{$returnAlias}.deleted_at")
            ->select("{$returnAlias}.customer_id")
            ->selectRaw(
                "SUM(CASE WHEN {$entryAlias}.return_condition IN (?, ?) THEN 1 ELSE 0 END) AS returned",
                [ReturnCondition::Good->value, ReturnCondition::Damaged->value],
            )
            ->selectRaw(
                "SUM(CASE WHEN {$entryAlias}.return_condition = ? THEN 1 ELSE 0 END) AS lost",
                [ReturnCondition::Lost->value],
            )
            ->groupBy("{$returnAlias}.customer_id");
    }

    /**
     * @return class-string<Model>
     */
    private function customerModel(): string
    {
        $model = Config::get('storix.models.customer');

        if (! is_string($model) || ! is_a($model, Model::class, true)) {
            throw new LogicException('The configured Storix customer model must be an Eloquent model class.');
        }

        return $model;
    }

    /**
     * @param  class-string<Model>  $fallback
     */
    private function model(string $key, string $fallback): Model
    {
        $model = $this->modelClass($key, $fallback);

        return new $model();
    }

    /**
     * @param  class-string<Model>  $fallback
     * @return class-string<Model>
     */
    private function modelClass(string $key, string $fallback): string
    {
        $model = Config::get("storix.models.{$key}", $fallback);

        if (is_string($model) && is_a($model, Model::class, true)) {
            return $model;
        }

        return $fallback;
    }
}
