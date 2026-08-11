<?php

declare(strict_types=1);

namespace Storix\Data;

final readonly class CrossReturnReconciliationResult
{
    public const string CONFIRMED_CROSS_RETURN = 'confirmed_cross_return';

    public const string DISCREPANCY = 'discrepancy';

    public const string RECONCILABLE_DRY_RUN = 'reconcilable_dry_run';

    public const string RECONCILED = 'reconciled';

    public const string SKIPPED = 'skipped';

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $status,
        public bool $databaseCorrection,
        public string $reason,
        public array $context,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toLogContext(): array
    {
        return [
            'status' => $this->status,
            ...$this->context,
            'database_correction' => $this->databaseCorrection,
            'reason' => $this->reason,
        ];
    }
}
