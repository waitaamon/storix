<?php

declare(strict_types=1);

namespace Storix\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Config;
use Override;
use Storix\Database\Factories\DispatchEntryFactory;
use Storix\Enums\ReturnCondition;
use Storix\Support\TableNames;

/**
 * @property int $id
 * @property int|string $dispatch_id
 * @property int|string $container_id
 * @property int|string|null $received_by
 * @property CarbonImmutable|null $return_date
 * @property ReturnCondition|null $return_condition
 * @property string|null $return_note
 * @property array<string, mixed>|null $metadata
 * @property-read Container $container
 * @property-read Dispatch $dispatch
 */
#[UseFactory(DispatchEntryFactory::class)]
#[Fillable([
    'dispatch_id',
    'container_id',
    'received_by',
    'return_date',
    'return_condition',
    'return_note',
    'metadata',
])]
final class DispatchEntry extends Model
{
    /** @use HasFactory<DispatchEntryFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the container that this entry belongs to.
     *
     * @return BelongsTo<Model, $this>
     */
    public function container(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.container', Container::class);

        return $this->belongsTo($model, 'container_id');
    }

    /**
     * Get the dispatch that this entry belongs to.
     *
     * @return BelongsTo<Model, $this>
     */
    public function dispatch(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.dispatch', Dispatch::class);

        return $this->belongsTo($model, 'dispatch_id');
    }

    /**
     * Get the user that received this entry.
     *
     * @return BelongsTo<Model, $this>
     */
    public function receivedBy(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.user', 'App\\Models\\User');

        return $this->belongsTo($model, 'received_by');
    }

    /**
     * Get the table associated with the model.
     */
    #[Override]
    public function getTable(): string
    {
        return TableNames::dispatchEntries();
    }

    /**
     * Get the casts for the model's attributes.
     *
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'return_date' => 'immutable_date',
            'return_condition' => ReturnCondition::class,
            'metadata' => 'array',
        ];
    }
}
