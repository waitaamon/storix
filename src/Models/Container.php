<?php

declare(strict_types=1);

namespace Storix\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Config;
use Storix\Database\Factories\ContainerFactory;
use Storix\Models\States\DispatchApprovedState;
use Storix\Support\TableNames;

#[UseFactory(ContainerFactory::class)]
final class Container extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'serial', 'is_active', 'description', 'metadata'];

    /**
     * Get the dispatch entries for this container.
     *
     * @return HasMany<DispatchEntry, self>
     */
    public function entries(): HasMany
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.dispatch_entry', 'Storix\\Models\\DispatchEntry');

        return $this->hasMany($model, 'container_id');
    }

    /**
     * Get the dispatches for this container.
     *
     * @return BelongsToMany<Dispatch, self>
     */
    public function dispatches(): BelongsToMany
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.dispatch', 'Storix\\Models\\Dispatch');

        return $this->belongsToMany($model, TableNames::dispatchEntries())
            ->using(Config::string('storix.models.dispatch_entry', 'Storix\\Models\\DispatchEntry'))
            ->withPivot(['received_by', 'return_date', 'return_condition', 'return_note'])
            ->withTimestamps();
    }

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return TableNames::containers();
    }

    /**
     * Scope a query to only include containers that are active and not currently dispatched.
     */
    #[Scope]
    protected function availableForDispatch(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereDoesntHave('entries', fn (Builder $query): Builder => $query->whereNull('return_date')
                ->whereHas('dispatch', fn (Builder $query): Builder => $query->whereState('state', DispatchApprovedState::class))
            );
    }

    /**
     * Scope a query to only include containers that are active and are currently dispatched.
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
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
