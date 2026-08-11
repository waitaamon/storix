<?php

declare(strict_types=1);

namespace Storix\Actions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Storix\Data\CrossReturnReconciliationResult;
use Storix\Models\Container;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\Dispatch;
use Storix\Models\DispatchEntry;
use Storix\Models\States\ContainerReturnApprovedState;
use Storix\Models\States\DispatchApprovedState;
use Throwable;

final readonly class ReconcileCrossReturnAction
{
    public function __construct(
        private DatabaseManager $database,
        private Repository $config,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(int|string $entryId, bool $dryRun = false): CrossReturnReconciliationResult
    {
        $entryClass = $this->modelClass('container_return_entry', ContainerReturnEntry::class);
        $connection = (new $entryClass)->getConnectionName();
        $attempts = max(1, (int) $this->config->get('storix.cross_return_reconciliation.deadlock_retries', 3));

        return $this->database->connection($connection)->transaction(
            fn (): CrossReturnReconciliationResult => $this->reconcile($entryId, $dryRun),
            $attempts,
        );
    }

    private function reconcile(int|string $entryId, bool $dryRun): CrossReturnReconciliationResult
    {
        $entryClass = $this->modelClass('container_return_entry', ContainerReturnEntry::class);
        $returnClass = $this->modelClass('container_return', ContainerReturn::class);
        $containerClass = $this->modelClass('container', Container::class);
        $dispatchEntryClass = $this->modelClass('dispatch_entry', DispatchEntry::class);
        $dispatchClass = $this->modelClass('dispatch', Dispatch::class);
        $customerClass = $this->modelClass('customer');

        $context = $this->emptyContext($entryId);
        $entry = $entryClass::query()->whereKey($entryId)->lockForUpdate()->first();

        if (! $entry instanceof Model) {
            return $this->result(
                CrossReturnReconciliationResult::SKIPPED,
                'The candidate entry no longer exists.',
                $context,
            );
        }

        $crossReturnBefore = (bool) $entry->getAttribute('cross_return');
        $context['cross_return'] = [
            'before' => $crossReturnBefore,
            'after' => $crossReturnBefore,
        ];

        if (! $crossReturnBefore) {
            return $this->result(
                CrossReturnReconciliationResult::SKIPPED,
                'The entry is no longer marked as a cross return.',
                $context,
            );
        }

        $returnId = $entry->getAttribute('container_return_id');
        $containerId = $entry->getAttribute('container_id');
        $context['container_return']['id'] = $returnId;
        $context['container']['id'] = $containerId;

        if (! is_int($returnId) && ! is_string($returnId)) {
            return $this->discrepancy('The entry has no valid container-return identifier.', $context);
        }

        if (! is_int($containerId) && ! is_string($containerId)) {
            return $this->discrepancy('The entry has no valid container identifier.', $context);
        }

        $containerReturn = $returnClass::query()->whereKey($returnId)->lockForUpdate()->first();

        if (! $containerReturn instanceof Model) {
            return $this->discrepancy('The parent container return is missing or deleted.', $context);
        }

        $context['container_return']['code'] = $containerReturn->getAttribute('code');
        $context['container_return']['transaction_date'] = $this->dateString(
            $containerReturn->getAttribute('transaction_date'),
        );

        if ((string) $containerReturn->getAttribute('state') !== ContainerReturnApprovedState::$name) {
            return $this->result(
                CrossReturnReconciliationResult::SKIPPED,
                'The parent container return is no longer approved.',
                $context,
            );
        }

        $returnDate = $this->date($containerReturn->getAttribute('transaction_date'));

        if (! $returnDate instanceof CarbonImmutable) {
            return $this->discrepancy('The approved container return has no valid transaction date.', $context);
        }

        $container = $containerClass::query()->whereKey($containerId)->lockForUpdate()->first();

        if (! $container instanceof Model) {
            return $this->discrepancy('The required container is missing or deleted.', $context);
        }

        $serial = $container->getAttribute('serial');
        $context['container']['serial'] = is_scalar($serial) ? (string) $serial : null;

        if (! is_string($context['container']['serial']) || mb_trim($context['container']['serial']) === '') {
            return $this->discrepancy('The required container serial is missing.', $context);
        }

        $returnCustomerId = $containerReturn->getAttribute('customer_id');
        $returnCustomer = $this->findCustomer($customerClass, $returnCustomerId);
        $context['returning_customer'] = $this->customerContext($returnCustomerId, $returnCustomer);

        if (! $returnCustomer instanceof Model || $context['returning_customer']['name'] === null) {
            return $this->discrepancy('The returning customer information is missing or inconsistent.', $context);
        }

        $previousDispatchEntryId = $entry->getAttribute('dispatch_entry_id');

        if ($previousDispatchEntryId !== null) {
            if (! is_int($previousDispatchEntryId) && ! is_string($previousDispatchEntryId)) {
                return $this->discrepancy('The previously linked dispatch-entry identifier is invalid.', $context);
            }

            $previous = $dispatchEntryClass::query()
                ->whereKey($previousDispatchEntryId)
                ->lockForUpdate()
                ->first();

            if (! $previous instanceof Model) {
                $context['previous_dispatch']['entry_id'] = $previousDispatchEntryId;

                return $this->discrepancy('The previously linked dispatch entry is missing or deleted.', $context);
            }

            if ((string) $previous->getAttribute('container_id') !== (string) $containerId) {
                $context['previous_dispatch']['entry_id'] = $previousDispatchEntryId;

                return $this->discrepancy('The previously linked dispatch entry belongs to another container.', $context);
            }

            $previousCycle = $this->dispatchCycle(
                $previous,
                $dispatchClass,
                $customerClass,
                true,
            );
            $context['previous_dispatch'] = $previousCycle['context'];

            if ($previousCycle['error'] !== null) {
                return $this->discrepancy($previousCycle['error'], $context);
            }

            if (! $this->date($previousCycle['dispatch']?->getAttribute('dispatched_at')) instanceof CarbonImmutable) {
                return $this->discrepancy(
                    'The previously linked dispatch has no valid physical dispatch timestamp.',
                    $context,
                );
            }
        }

        $dispatchEntryTable = (new $dispatchEntryClass)->getTable();
        $dispatchTable = (new $dispatchClass)->getTable();
        $dispatchAlias = 'storix_reconciliation_dispatches';
        $missingTimestampEntry = $dispatchEntryClass::query()
            ->select("{$dispatchEntryTable}.*")
            ->join(
                "{$dispatchTable} as {$dispatchAlias}",
                "{$dispatchAlias}.id",
                '=',
                "{$dispatchEntryTable}.dispatch_id",
            )
            ->where("{$dispatchEntryTable}.container_id", $containerId)
            ->where("{$dispatchAlias}.state", DispatchApprovedState::$name)
            ->whereNull("{$dispatchAlias}.deleted_at")
            ->whereNull("{$dispatchAlias}.dispatched_at")
            ->orderByDesc("{$dispatchEntryTable}.id")
            ->lockForUpdate()
            ->first();

        if ($missingTimestampEntry instanceof Model) {
            $missingTimestampCycle = $this->dispatchCycle(
                $missingTimestampEntry,
                $dispatchClass,
                $customerClass,
                true,
            );
            $context['selected_dispatch'] = $missingTimestampCycle['context'];

            return $this->discrepancy(
                'An approved dispatch has no physical dispatch timestamp, so the latest cycle cannot be proved.',
                $context,
            );
        }

        $eligibleDispatches = $dispatchEntryClass::query()
            ->select("{$dispatchEntryTable}.*")
            ->addSelect([
                "{$dispatchAlias}.dispatched_at as reconciliation_dispatched_at",
            ])
            ->join(
                "{$dispatchTable} as {$dispatchAlias}",
                "{$dispatchAlias}.id",
                '=',
                "{$dispatchEntryTable}.dispatch_id",
            )
            ->where("{$dispatchEntryTable}.container_id", $containerId)
            ->where("{$dispatchAlias}.state", DispatchApprovedState::$name)
            ->whereNull("{$dispatchAlias}.deleted_at")
            ->whereNotNull("{$dispatchAlias}.dispatched_at")
            ->where("{$dispatchAlias}.dispatched_at", '<', $returnDate->addDay())
            ->orderByDesc("{$dispatchAlias}.dispatched_at")
            ->orderByDesc("{$dispatchEntryTable}.id")
            ->lockForUpdate()
            ->limit(2)
            ->get();

        if ($eligibleDispatches->isEmpty()) {
            return $this->discrepancy(
                'No approved physical dispatch exists on or before the return transaction date.',
                $context,
            );
        }

        $selectedEntry = $eligibleDispatches->firstOrFail();

        $selectedCycle = $this->dispatchCycle(
            $selectedEntry,
            $dispatchClass,
            $customerClass,
            true,
        );
        $context['selected_dispatch'] = $selectedCycle['context'];

        if ($selectedCycle['error'] !== null) {
            return $this->discrepancy($selectedCycle['error'], $context);
        }

        $selectedTimestamp = $this->date($selectedCycle['dispatch']?->getAttribute('dispatched_at'));

        if (! $selectedTimestamp instanceof CarbonImmutable) {
            return $this->discrepancy(
                'The selected approved dispatch has no valid physical dispatch timestamp.',
                $context,
            );
        }

        $secondEntry = $eligibleDispatches->get(1);

        if ($secondEntry instanceof Model) {
            $secondTimestamp = $this->date($secondEntry->getAttribute('reconciliation_dispatched_at'));

            if ($secondTimestamp instanceof CarbonImmutable && $secondTimestamp->equalTo($selectedTimestamp)) {
                return $this->discrepancy(
                    'Multiple approved dispatch entries share the latest physical dispatch timestamp.',
                    $context,
                );
            }
        }

        if ($selectedTimestamp->toDateString() === $returnDate->toDateString()) {
            return $this->discrepancy(
                'The dispatch and return share a transaction date, and the date-only return cannot prove event order.',
                $context,
            );
        }

        $selectedEntryId = $selectedEntry->getKey();
        $linkedApprovedReturn = $this->linkedReturnEntry(
            $entryClass,
            $returnClass,
            $selectedEntryId,
            $entryId,
            true,
        );

        if ($linkedApprovedReturn instanceof Model) {
            return $this->discrepancy(
                'The proposed dispatch entry is already linked to another approved return.',
                $context,
            );
        }

        $linkedOtherReturn = $this->linkedReturnEntry(
            $entryClass,
            $returnClass,
            $selectedEntryId,
            $entryId,
            false,
        );

        if ($linkedOtherReturn instanceof Model) {
            return $this->discrepancy(
                'The proposed dispatch entry is already linked to another return, so the required linkage is inconsistent.',
                $context,
            );
        }

        if ($this->hasInterveningApprovedReturn(
            $entryClass,
            $returnClass,
            $containerId,
            $entryId,
            $selectedTimestamp,
            $returnDate,
        )) {
            return $this->discrepancy(
                'Another approved return may occur between the proposed dispatch and this return.',
                $context,
            );
        }

        $selectedDispatch = $selectedCycle['dispatch'];
        $selectedCustomerId = $selectedDispatch?->getAttribute('customer_id');
        $expectedCrossReturn = (string) $selectedCustomerId !== (string) $returnCustomerId;
        $context['cross_return']['after'] = $expectedCrossReturn;

        $changes = [];

        if ((string) $previousDispatchEntryId !== (string) $selectedEntryId) {
            $changes['dispatch_entry_id'] = [
                'before' => $previousDispatchEntryId,
                'after' => $selectedEntryId,
            ];
        }

        if ($crossReturnBefore !== $expectedCrossReturn) {
            $changes['cross_return'] = [
                'before' => $crossReturnBefore,
                'after' => $expectedCrossReturn,
            ];
        }

        $context['changes'] = $changes;

        if ($changes === []) {
            return $this->result(
                CrossReturnReconciliationResult::CONFIRMED_CROSS_RETURN,
                'The linked dispatch is the latest approved physical cycle and its customer differs from the returning customer; the cross return is confirmed.',
                $context,
            );
        }

        if (! $dryRun) {
            $updates = [];

            if (array_key_exists('dispatch_entry_id', $changes)) {
                $updates['dispatch_entry_id'] = $selectedEntryId;
            }

            if (array_key_exists('cross_return', $changes)) {
                $updates['cross_return'] = $expectedCrossReturn;
            }

            $entry->forceFill($updates)->saveQuietly();
        }

        $linkageChanged = array_key_exists('dispatch_entry_id', $changes);
        $reason = match (true) {
            $expectedCrossReturn => 'The latest approved physical dispatch cycle was selected and the linkage was corrected; the differing customers confirm a genuine cross return.',
            $linkageChanged => 'The latest approved physical dispatch cycle belongs to the returning customer, so the linkage was corrected and the false cross-return flag was cleared.',
            default => 'The linked dispatch is the latest approved physical cycle and belongs to the returning customer, so the false cross-return flag was cleared.',
        };

        return $this->result(
            $dryRun
                ? CrossReturnReconciliationResult::RECONCILABLE_DRY_RUN
                : CrossReturnReconciliationResult::RECONCILED,
            $reason,
            $context,
            ! $dryRun,
        );
    }

    /**
     * @param  class-string<Model>  $entryClass
     * @param  class-string<Model>  $returnClass
     */
    private function hasInterveningApprovedReturn(
        string $entryClass,
        string $returnClass,
        int|string $containerId,
        int|string $entryId,
        CarbonImmutable $dispatchTimestamp,
        CarbonImmutable $returnDate,
    ): bool {
        $entryTable = (new $entryClass)->getTable();
        $returnTable = (new $returnClass)->getTable();
        $returnAlias = 'storix_reconciliation_intervening_returns';

        $entry = $entryClass::query()
            ->select("{$entryTable}.*")
            ->join(
                "{$returnTable} as {$returnAlias}",
                "{$returnAlias}.id",
                '=',
                "{$entryTable}.container_return_id",
            )
            ->where("{$entryTable}.container_id", $containerId)
            ->where("{$entryTable}.id", '!=', $entryId)
            ->where("{$returnAlias}.state", ContainerReturnApprovedState::$name)
            ->whereNull("{$returnAlias}.deleted_at")
            ->where("{$returnAlias}.transaction_date", '>=', $dispatchTimestamp->toDateString())
            ->where("{$returnAlias}.transaction_date", '<=', $returnDate->toDateString())
            ->lockForUpdate()
            ->first();

        return $entry instanceof Model;
    }

    /**
     * @param  class-string<Model>  $entryClass
     * @param  class-string<Model>  $returnClass
     */
    private function linkedReturnEntry(
        string $entryClass,
        string $returnClass,
        int|string $dispatchEntryId,
        int|string $candidateEntryId,
        bool $approvedOnly,
    ): ?Model {
        $entryTable = (new $entryClass)->getTable();
        $returnTable = (new $returnClass)->getTable();
        $returnAlias = 'storix_reconciliation_linked_returns';
        $query = $entryClass::query()
            ->select("{$entryTable}.*")
            ->join(
                "{$returnTable} as {$returnAlias}",
                "{$returnAlias}.id",
                '=',
                "{$entryTable}.container_return_id",
            )
            ->where("{$entryTable}.dispatch_entry_id", $dispatchEntryId)
            ->where("{$entryTable}.id", '!=', $candidateEntryId)
            ->whereNull("{$returnAlias}.deleted_at");

        if ($approvedOnly) {
            $query->where("{$returnAlias}.state", ContainerReturnApprovedState::$name);
        }

        $entry = $query->lockForUpdate()->first();

        return $entry instanceof Model ? $entry : null;
    }

    /**
     * @param  class-string<Model>  $dispatchClass
     * @param  class-string<Model>  $customerClass
     * @return array{context: array<string, mixed>, dispatch: Model|null, error: string|null}
     */
    private function dispatchCycle(
        Model $entry,
        string $dispatchClass,
        string $customerClass,
        bool $requireApproved,
    ): array {
        $context = [
            'entry_id' => $entry->getKey(),
            'code' => null,
            'customer' => [
                'id' => null,
                'name' => null,
            ],
            'physical_timestamp' => null,
        ];
        $dispatchId = $entry->getAttribute('dispatch_id');

        if (! is_int($dispatchId) && ! is_string($dispatchId)) {
            return [
                'context' => $context,
                'dispatch' => null,
                'error' => 'A required dispatch identifier is missing or invalid.',
            ];
        }

        $dispatch = $dispatchClass::query()->whereKey($dispatchId)->lockForUpdate()->first();

        if (! $dispatch instanceof Model) {
            return [
                'context' => $context,
                'dispatch' => null,
                'error' => 'A required dispatch is missing or deleted.',
            ];
        }

        $customerId = $dispatch->getAttribute('customer_id');
        $customer = $this->findCustomer($customerClass, $customerId);
        $context['code'] = $dispatch->getAttribute('code');
        $context['customer'] = $this->customerContext($customerId, $customer);
        $context['physical_timestamp'] = $this->timestampString($dispatch->getAttribute('dispatched_at'));

        if ($requireApproved && (string) $dispatch->getAttribute('state') !== DispatchApprovedState::$name) {
            return [
                'context' => $context,
                'dispatch' => $dispatch,
                'error' => 'A required dispatch is not approved.',
            ];
        }

        if (! $customer instanceof Model || $context['customer']['name'] === null) {
            return [
                'context' => $context,
                'dispatch' => $dispatch,
                'error' => 'A required dispatch customer is missing or inconsistent.',
            ];
        }

        return [
            'context' => $context,
            'dispatch' => $dispatch,
            'error' => null,
        ];
    }

    /**
     * @param  class-string<Model>  $customerClass
     */
    private function findCustomer(string $customerClass, mixed $customerId): ?Model
    {
        if (! is_int($customerId) && ! is_string($customerId)) {
            return null;
        }

        $customer = $customerClass::query()->whereKey($customerId)->first();

        return $customer instanceof Model ? $customer : null;
    }

    /**
     * @return array{id: mixed, name: string|null}
     */
    private function customerContext(mixed $customerId, ?Model $customer): array
    {
        $name = $customer?->getAttribute('name');

        return [
            'id' => $customerId,
            'name' => is_scalar($name) && mb_trim((string) $name) !== '' ? (string) $name : null,
        ];
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        try {
            if ($value instanceof CarbonImmutable) {
                return $value;
            }

            if ($value instanceof CarbonInterface) {
                return CarbonImmutable::instance($value);
            }

            if (is_string($value) && mb_trim($value) !== '') {
                return CarbonImmutable::parse($value);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function dateString(mixed $value): ?string
    {
        return $this->date($value)?->toDateString();
    }

    private function timestampString(mixed $value): ?string
    {
        return $this->date($value)?->toIso8601String();
    }

    /**
     * @return class-string<Model>
     */
    private function modelClass(string $key, ?string $default = null): string
    {
        $class = $this->config->get("storix.models.{$key}", $default);

        if (! is_string($class) || ! is_a($class, Model::class, true)) {
            throw new RuntimeException("The configured Storix [{$key}] model must be an Eloquent model class.");
        }

        return $class;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyContext(int|string $entryId): array
    {
        return [
            'container' => [
                'id' => null,
                'serial' => null,
            ],
            'container_return' => [
                'id' => null,
                'entry_id' => $entryId,
                'code' => null,
                'transaction_date' => null,
            ],
            'returning_customer' => [
                'id' => null,
                'name' => null,
            ],
            'previous_dispatch' => [
                'entry_id' => null,
                'code' => null,
                'customer' => [
                    'id' => null,
                    'name' => null,
                ],
                'physical_timestamp' => null,
            ],
            'selected_dispatch' => [
                'entry_id' => null,
                'code' => null,
                'customer' => [
                    'id' => null,
                    'name' => null,
                ],
                'physical_timestamp' => null,
            ],
            'cross_return' => [
                'before' => true,
                'after' => true,
            ],
            'changes' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function discrepancy(string $reason, array $context): CrossReturnReconciliationResult
    {
        return $this->result(CrossReturnReconciliationResult::DISCREPANCY, $reason, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function result(
        string $status,
        string $reason,
        array $context,
        bool $databaseCorrection = false,
    ): CrossReturnReconciliationResult {
        return new CrossReturnReconciliationResult(
            status: $status,
            databaseCorrection: $databaseCorrection,
            reason: $reason,
            context: $context,
        );
    }
}
