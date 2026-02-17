<?php

declare(strict_types=1);

namespace Storix\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Config;
use Storix\Database\Factories\ContainerFactory;
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
     * @return BelongsToMany<Dispatch>
     */
    public function dispatches(): BelongsToMany
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.dispatch', 'Storix\\Models\\Dispatch');

        return $this->belongsToMany($model, TableNames::dispatchEntries())
            ->using(Config::string('storix.models.dispatchEntry', 'Storix\\Models\\DispatchEntry'))
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
            ->whereDoesntHave('dispatches', fn (Builder $query) => $query->whereNull('return_date'));
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
