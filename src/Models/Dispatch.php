<?php

declare(strict_types=1);

namespace Storix\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Config;
use Override;
use Spatie\ModelStates\HasStates;
use Spatie\ModelStates\HasStatesContract;
use Storix\Database\Factories\DispatchFactory;
use Storix\Models\States\DispatchState;
use Storix\Support\TableNames;

/**
 * @property int $id
 * @property int|string $delivery_note_id
 * @property int|string|null $dispatched_by
 * @property string|null $code
 * @property string|null $idempotency_key
 * @property string|null $idempotency_fingerprint
 * @property CarbonInterface|null $dispatched_at
 * @property string|null $dispatch_note
 * @property DispatchState $state
 * @property int|string|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property int|string|null $voided_by
 * @property CarbonImmutable|null $voided_at
 * @property string|null $void_reason
 * @property array<string, mixed>|null $metadata
 * @property-read Collection<int, DispatchEntry> $entries
 */
#[UseFactory(DispatchFactory::class)]
#[Fillable([
    'delivery_note_id',
    'dispatched_by',
    'code',
    'idempotency_key',
    'idempotency_fingerprint',
    'dispatched_at',
    'dispatch_note',
    'state',
    'approved_by',
    'approved_at',
    'voided_by',
    'voided_at',
    'void_reason',
    'metadata',
])]
final class Dispatch extends Model implements HasStatesContract
{
    /** @use HasFactory<DispatchFactory> */
    use HasFactory, HasStates, SoftDeletes;

    /**
     * Get the delivery note associated with the dispatch.
     *
     * @return BelongsTo<Model, $this>
     */
    public function deliveryNote(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.delivery_note', 'App\\Models\\Sales\\DeliveryNote');

        return $this->belongsTo($model, 'delivery_note_id');
    }

    /**
     * Get the user that dispatched the items.
     *
     * @return BelongsTo<Model, $this>
     */
    public function dispatchedBy(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.user', 'App\\Models\\User');

        return $this->belongsTo($model, 'dispatched_by');
    }

    /**
     * Get the user that approved this dispatch.
     *
     * @return BelongsTo<Model, $this>
     */
    public function approvedBy(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.user', 'App\\Models\\User');

        return $this->belongsTo($model, 'approved_by');
    }

    /**
     * Get the user that voided this dispatch.
     *
     * @return BelongsTo<Model, $this>
     */
    public function voidedBy(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.user', 'App\\Models\\User');

        return $this->belongsTo($model, 'voided_by');
    }

    /**
     * Get all the entries for the dispatch.
     *
     * @return HasMany<Model, $this>
     */
    public function entries(): HasMany
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.dispatch_entry', DispatchEntry::class);

        return $this->hasMany($model, 'dispatch_id');
    }

    /**
     * Get all the containers for the dispatch.
     *
     * @return BelongsToMany<Model, $this>
     */
    public function containers(): BelongsToMany
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.container', Container::class);

        return $this->belongsToMany($model, TableNames::dispatchEntries())
            ->withPivot('id', 'received_by', 'return_date', 'return_condition', 'return_note', 'deleted_at')
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    /**
     * Get the name of the table associated with the model.
     */
    #[Override]
    public function getTable(): string
    {
        return TableNames::dispatches();
    }

    /**
     * The "booted" method of the model.
     */
    #[Override]
    protected static function booted(): void
    {
        self::creating(function (self $dispatch): void {
            if ($dispatch->dispatched_by === null && auth()->check()) {
                $dispatch->dispatched_by = auth()->id();
            }

            $dispatch->dispatched_at ??= now();
        });

        self::created(function (self $dispatch): void {
            if (empty($dispatch->code)) {
                $dispatchedAt = $dispatch->dispatched_at ?? now();
                $dispatch->code = 'DSP-'.$dispatchedAt->format('ymd').str((string) $dispatch->id)->padLeft(4, '0');
                $dispatch->saveQuietly();
            }
        });
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'dispatched_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
            'metadata' => 'array',
            'state' => DispatchState::class,
        ];
    }
}
