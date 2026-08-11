<?php

declare(strict_types=1);

namespace Storix\Support;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Log\LogManager;
use Illuminate\Support\Str;
use LogicException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Storix\Data\CrossReturnReconciliationResult;
use Throwable;

final class CrossReturnReconciliationLogger
{
    private ?string $reportPath = null;

    private ?string $runId = null;

    private ?LoggerInterface $logger = null;

    public function __construct(
        private readonly Filesystem $files,
        private readonly Repository $config,
        private readonly LogManager $logManager,
    ) {}

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function start(bool $dryRun, array $configuration): string
    {
        $directory = $this->config->get(
            'storix.cross_return_reconciliation.report_directory',
            storage_path('logs/storix/cross-return-reconciliation'),
        );

        if (! is_string($directory) || mb_trim($directory) === '') {
            throw new RuntimeException('The Storix cross-return reconciliation report directory must be a non-empty path.');
        }

        $directory = mb_rtrim($directory, DIRECTORY_SEPARATOR);

        if (! $this->files->isDirectory($directory)
            && ! $this->files->makeDirectory($directory, 0755, true, true)) {
            throw new RuntimeException("Unable to create the Storix reconciliation report directory [{$directory}].");
        }

        $startedAt = CarbonImmutable::now();
        $this->runId = (string) Str::ulid();
        $filename = 'cross-return-reconciliation-'
            .$startedAt->format('Ymd_His_u')
            .'-'.$this->runId.'.log';
        $reportPath = $directory.DIRECTORY_SEPARATOR.$filename;
        $this->reportPath = $reportPath;

        if ($this->files->put($reportPath, '') === false) {
            throw new RuntimeException("Unable to create the Storix reconciliation report [{$reportPath}].");
        }

        $environment = $this->config->get('app.env', 'production');
        $channelName = is_string($environment) && mb_trim($environment) !== ''
            ? $environment
            : 'production';

        $this->logger = $this->logManager->build([
            'driver' => 'single',
            'path' => $reportPath,
            'level' => LogLevel::DEBUG,
            'name' => $channelName,
            'locking' => true,
            'replace_placeholders' => true,
        ]);

        $this->write('start', LogLevel::INFO, 'Storix cross-return reconciliation started.', [
            'started_at' => $startedAt->toIso8601String(),
            'dry_run' => $dryRun,
            'configuration' => $configuration,
        ]);

        return $reportPath;
    }

    public function candidate(CrossReturnReconciliationResult $result): void
    {
        $level = $result->status === CrossReturnReconciliationResult::DISCREPANCY
            ? LogLevel::WARNING
            : LogLevel::INFO;

        $this->write(
            'candidate',
            $level,
            'Storix cross-return reconciliation candidate evaluated.',
            $result->toLogContext(),
        );
    }

    public function candidateFailure(int|string $entryId, Throwable $exception): void
    {
        $this->write('candidate', LogLevel::ERROR, 'Storix cross-return reconciliation candidate failed.', [
            'status' => 'failed',
            'container_return_entry_id' => $entryId,
            'database_correction' => false,
            'reason' => 'Candidate processing failed; no correction was made.',
            'exception' => [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
            ],
        ]);
    }

    public function processingFailure(Throwable $exception): void
    {
        $this->write('exception', LogLevel::ERROR, 'Storix cross-return reconciliation processing failed.', [
            'status' => 'failed',
            'reason' => 'Cross-return candidate iteration failed.',
            'exception' => [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
            ],
        ]);
    }

    /**
     * @param  array<string, int>  $totals
     */
    public function complete(array $totals, float $durationSeconds): void
    {
        $this->write(
            'completion',
            $totals['failed'] > 0 ? LogLevel::ERROR : LogLevel::INFO,
            'Storix cross-return reconciliation completed.',
            [
                'completed_at' => CarbonImmutable::now()->toIso8601String(),
                'totals' => $totals,
                'duration_seconds' => round($durationSeconds, 6),
            ],
        );
    }

    public function reportPath(): string
    {
        return $this->reportPath
            ?? throw new LogicException('The Storix reconciliation report has not been started.');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function write(string $event, string $level, string $message, array $context): void
    {
        if ($this->reportPath === null || $this->runId === null || ! $this->logger instanceof LoggerInterface) {
            throw new LogicException('The Storix reconciliation report has not been started.');
        }

        $this->logger->log($level, $message, [
            'event' => $event,
            'run_id' => $this->runId,
            ...$context,
        ]);
    }
}
