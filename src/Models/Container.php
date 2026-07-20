<?php

declare(strict_types=1);

namespace Storix\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Config;
use Override;
use Storix\Database\Factories\ContainerFactory;
use Storix\Models\States\DispatchApprovedState;
use Storix\Support\TableNames;

/**
 * @property int $id
 * @property string $name
 * @property string $serial
 * @property bool $is_active
 * @property string $replacement_cost
 * @property string $replacement_currency
 * @property string|null $description
 * @property array<string, mixed>|null $metadata
 * @property-read Collection<int, DispatchEntry> $entries
 */
#[UseFactory(ContainerFactory::class)]
#[Fillable([
    'name',
    'serial',
    'is_active',
    'replacement_cost',
    'replacement_currency',
    'description',
    'metadata',
])]
final class Container extends Model
{
    /** @use HasFactory<ContainerFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the dispatch entries for this container.
     *
     * @return HasMany<Model, $this>
     */
    public function entries(): HasMany
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.dispatch_entry', DispatchEntry::class);

        return $this->hasMany($model, 'container_id');
    }

    /**
     * Get the dispatches for this container.
     *
     * @return BelongsToMany<Model, $this>
     */
    public function dispatches(): BelongsToMany
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.dispatch', Dispatch::class);

        return $this->belongsToMany($model, TableNames::dispatchEntries())
            ->withPivot(['id', 'received_by', 'return_date', 'return_condition', 'return_note', 'deleted_at'])
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    /**
     * Get the table associated with the model.
     */
    #[Override]
    public function getTable(): string
    {
        return TableNames::containers();
    }

    /**
     * Scope a query to only include active containers that are not reserved or dispatched.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function availableForDispatch(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereDoesntHave('entries', fn (Builder $query): Builder => $query->whereNull('return_date'));
    }

    /**
     * Scope a query to only include containers that are active and are currently dispatched.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function currentlyDispatched(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereHas('entries', fn (Builder $query): Builder => $query->whereNull('return_date')
                ->whereHas('dispatch', fn (Builder $query): Builder => $query->whereState('state', DispatchApprovedState::class))
            );
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'replacement_cost' => 'decimal:4',
            'metadata' => 'array',
        ];
    }
}
