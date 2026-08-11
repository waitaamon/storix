<?php

declare(strict_types=1);

namespace Storix\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Storix\Actions\ReconcileCrossReturnAction;
use Storix\Data\CrossReturnReconciliationResult;
use Storix\Models\ContainerReturn;
use Storix\Models\ContainerReturnEntry;
use Storix\Models\States\ContainerReturnApprovedState;
use Storix\Support\CrossReturnReconciliationLogger;
use Throwable;

#[Description('Reconcile approved cross-return entries against their physical dispatch cycles')]
#[Signature('storix:reconcile-cross-returns {--dry-run : Analyze and report without changing records}')]
final class ReconcileCrossReturnsCommand extends Command
{
    public function __construct(
        private readonly ReconcileCrossReturnAction $action,
        private readonly CrossReturnReconciliationLogger $logger,
        private readonly Repository $config,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $startedAt = hrtime(true);
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(1, (int) $this->config->get('storix.cross_return_reconciliation.chunk_size', 500));
        $deadlockRetries = max(1, (int) $this->config->get('storix.cross_return_reconciliation.deadlock_retries', 3));
        $totals = $this->emptyTotals();
        $reportFailed = false;

        try {
            $reportPath = $this->logger->start($dryRun, [
                'report_directory' => $this->config->get('storix.cross_return_reconciliation.report_directory'),
                'chunk_size' => $chunkSize,
                'deadlock_retries' => $deadlockRetries,
                'schedule_enabled' => (bool) $this->config->get('storix.cross_return_reconciliation.schedule.enabled', true),
                'schedule_timezone' => $this->config->get('storix.cross_return_reconciliation.schedule.timezone', 'Africa/Nairobi'),
            ]);
        } catch (Throwable $exception) {
            $this->error('Unable to create the cross-return reconciliation report: '.$exception->getMessage());

            return self::FAILURE;
        }

        try {
            foreach ($this->candidateEntries($chunkSize) as $candidate) {
                $entryId = $candidate->getKey();
                $totals['evaluated']++;

                try {
                    $result = $this->action->handle($entryId, $dryRun);
                    $this->logger->candidate($result);
                    $totals[$result->status]++;

                    if ($result->databaseCorrection) {
                        $totals['database_corrections']++;
                    }
                } catch (Throwable $exception) {
                    $totals['failed']++;
                    $this->logger->candidateFailure($entryId, $exception);
                    $this->warn("Cross-return entry [{$entryId}] failed: {$exception->getMessage()}");
                }
            }
        } catch (Throwable $exception) {
            $totals['failed']++;

            try {
                $this->logger->processingFailure($exception);
            } catch (Throwable $reportException) {
                $reportFailed = true;
                $this->error('Unable to write a reconciliation exception entry: '.$reportException->getMessage());
            }

            $this->error('Cross-return candidate processing failed: '.$exception->getMessage());
        }

        $durationSeconds = (hrtime(true) - $startedAt) / 1_000_000_000;

        try {
            $this->logger->complete($totals, $durationSeconds);
        } catch (Throwable $exception) {
            $reportFailed = true;
            $this->error('Unable to complete the cross-return reconciliation report: '.$exception->getMessage());
        }

        $this->line('Cross-return reconciliation report: '.$reportPath);
        $this->line(
            "Evaluated {$totals['evaluated']} candidate(s); "
            ."corrected {$totals['database_corrections']}; "
            ."discrepancies {$totals[CrossReturnReconciliationResult::DISCREPANCY]}; "
            ."failures {$totals['failed']}.",
        );

        return $totals['failed'] > 0 || $reportFailed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return iterable<int, Model>
     */
    private function candidateEntries(int $chunkSize): iterable
    {
        $entryClass = $this->modelClass('container_return_entry', ContainerReturnEntry::class);
        $returnClass = $this->modelClass('container_return', ContainerReturn::class);
        $entryTable = (new $entryClass)->getTable();
        $returnTable = (new $returnClass)->getTable();
        $returnAlias = 'storix_reconciliation_candidate_returns';

        return $entryClass::query()
            ->select("{$entryTable}.*")
            ->join(
                "{$returnTable} as {$returnAlias}",
                "{$returnAlias}.id",
                '=',
                "{$entryTable}.container_return_id",
            )
            ->where("{$entryTable}.cross_return", true)
            ->where("{$returnAlias}.state", ContainerReturnApprovedState::$name)
            ->whereNull("{$returnAlias}.deleted_at")
            ->lazyById($chunkSize, "{$entryTable}.id", 'id');
    }

    /**
     * @return class-string<Model>
     */
    private function modelClass(string $key, string $default): string
    {
        $class = $this->config->get("storix.models.{$key}", $default);

        if (! is_string($class) || ! is_a($class, Model::class, true)) {
            throw new RuntimeException("The configured Storix [{$key}] model must be an Eloquent model class.");
        }

        return $class;
    }

    /**
     * @return array<string, int>
     */
    private function emptyTotals(): array
    {
        return [
            'evaluated' => 0,
            CrossReturnReconciliationResult::RECONCILED => 0,
            CrossReturnReconciliationResult::RECONCILABLE_DRY_RUN => 0,
            CrossReturnReconciliationResult::CONFIRMED_CROSS_RETURN => 0,
            CrossReturnReconciliationResult::DISCREPANCY => 0,
            CrossReturnReconciliationResult::SKIPPED => 0,
            'failed' => 0,
            'database_corrections' => 0,
        ];
    }
}
