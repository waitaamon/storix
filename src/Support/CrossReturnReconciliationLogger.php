<?php

declare(strict_types=1);

namespace Storix\Support;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Storix\Data\CrossReturnReconciliationResult;
use Throwable;

final class CrossReturnReconciliationLogger
{
    private ?string $reportPath = null;

    private ?string $runId = null;

    public function __construct(private readonly Filesystem $files, private readonly Repository $config) {}

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

        $this->write('start', 'info', [
            'started_at' => $startedAt->toIso8601String(),
            'dry_run' => $dryRun,
            'configuration' => $configuration,
        ]);

        return $reportPath;
    }

    public function candidate(CrossReturnReconciliationResult $result): void
    {
        $this->write('candidate', 'info', $result->toLogContext());
    }

    public function candidateFailure(int|string $entryId, Throwable $exception): void
    {
        $this->write('candidate', 'error', [
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
        $this->write('exception', 'error', [
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
        $this->write('completion', $totals['failed'] > 0 ? 'error' : 'info', [
            'completed_at' => CarbonImmutable::now()->toIso8601String(),
            'totals' => $totals,
            'duration_seconds' => round($durationSeconds, 6),
        ]);
    }

    public function reportPath(): string
    {
        return $this->reportPath
            ?? throw new LogicException('The Storix reconciliation report has not been started.');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function write(string $event, string $level, array $context): void
    {
        if ($this->reportPath === null || $this->runId === null) {
            throw new LogicException('The Storix reconciliation report has not been started.');
        }

        $line = json_encode([
            'timestamp' => CarbonImmutable::now()->toIso8601String(),
            'level' => $level,
            'event' => $event,
            'run_id' => $this->runId,
            ...$context,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $line .= PHP_EOL;

        if ($this->files->append($this->reportPath, $line, true) !== mb_strlen($line, '8bit')) {
            throw new RuntimeException("Unable to write the Storix reconciliation report [{$this->reportPath}].");
        }
    }
}
