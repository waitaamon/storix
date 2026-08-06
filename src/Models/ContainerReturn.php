<?php

declare(strict_types=1);

namespace Storix\Models;

use Carbon\CarbonImmutable;
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
use Storix\Database\Factories\ContainerReturnFactory;
use Storix\Models\States\ContainerReturnState;
use Storix\Support\TableNames;

/**
 * @property int $id
 * @property string|null $code
 * @property int|string $customer_id
 * @property int|string $user_id
 * @property int|string|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property string|null $note
 * @property ContainerReturnState $state
 * @property CarbonImmutable $transaction_date
 * @property-read Model $customer
 * @property-read Model $user
 * @property-read Model|null $approvedBy
 * @property-read Collection<int, ContainerReturnEntry> $entries
 * @property-read Collection<int, Container> $containers
 * @property-read int|null $entries_count
 */
#[UseFactory(ContainerReturnFactory::class)]
#[Fillable([
    'code',
    'customer_id',
    'user_id',
    'approved_by',
    'approved_at',
    'note',
    'state',
    'transaction_date',
])]
final class ContainerReturn extends Model implements HasStatesContract
{
    /** @use HasFactory<ContainerReturnFactory> */
    use HasFactory, HasStates, SoftDeletes;

    /**
     * @return BelongsTo<Model, $this>
     */
    public function customer(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.customer', 'App\\Models\\Sales\\Customer');

        return $this->belongsTo($model, 'customer_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.user', 'App\\Models\\User');

        return $this->belongsTo($model, 'user_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function approvedBy(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.user', 'App\\Models\\User');

        return $this->belongsTo($model, 'approved_by');
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function entries(): HasMany
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.container_return_entry', ContainerReturnEntry::class);

        return $this->hasMany($model, 'container_return_id');
    }

    /**
     * @return BelongsToMany<Model, $this>
     */
    public function containers(): BelongsToMany
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.container', Container::class);

        return $this->belongsToMany($model, TableNames::containerReturnEntries())
            ->withPivot(['id', 'dispatch_entry_id', 'return_condition', 'note', 'cross_return'])
            ->withTimestamps();
    }

    #[Override]
    public function getTable(): string
    {
        return TableNames::containerReturns();
    }

    #[Override]
    protected static function booted(): void
    {
        self::creating(function (self $containerReturn): void {
            if ($containerReturn->getAttribute('user_id') === null && auth()->check()) {
                $containerReturn->setAttribute('user_id', auth()->id());
            }

            if ($containerReturn->getAttribute('transaction_date') === null) {
                $containerReturn->setAttribute('transaction_date', today()->toDateString());
            }
        });

        self::created(function (self $containerReturn): void {
            if (filled($containerReturn->code)) {
                return;
            }

            $containerReturn->code = 'CRN-'
                .$containerReturn->transaction_date->format('ymd')
                .str((string) $containerReturn->getKey())->padLeft(4, '0');
            $containerReturn->saveQuietly();
        });
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'approved_at' => 'immutable_datetime',
            'transaction_date' => 'immutable_date',
            'state' => ContainerReturnState::class,
        ];
    }
}
