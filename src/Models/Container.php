<?php

declare(strict_types=1);

namespace Storix\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Storix\Database\Factories\ContainerFactory;

final class Container extends Model
{
    /** @use HasFactory<ContainerFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     * @var list<string>
     */
    protected $fillable = ['name', 'serial', 'is_active', 'description'];

    /** Get the table name from config. */
    public function getTable(): string
    {
        return (string)config('storix.tables.containers', 'containers');
    }

    /**
     * Dispatch items linked to this container.
     *
     * @return HasMany<ContainerDispatchItem, self>
     */
    public function dispatchItems(): HasMany
    {
        return $this->hasMany(ContainerDispatchItem::class, 'container_id');
    }

    /**
     * Return items linked to this container.
     *
     * @return HasMany<ContainerReturnItem, self>
     */
    public function returnItems(): HasMany
    {
        return $this->hasMany(ContainerReturnItem::class, 'container_id');
    }

    /**
     * Scope to only active containers.
     *
     * @return Builder<Container>
     */
    #[Scope]
    public function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to containers currently dispatched to customers (not yet returned).
     *
     * @return Builder<Container>
     */
    #[Scope]
    public function withCustomers(Builder $query): Builder
    {
        return $query->whereHas('dispatchItems', static fn(Builder $query): Builder => $query->whereDoesntHave('returnItem')
        );
    }

    /**
     * Scope to active containers with no open (unreturned) dispatch.
     *
     * @return Builder<Container>
     */
    #[Scope]
    public function availableForDispatch(Builder $query): Builder
    {
        return $query->active()->whereDoesntHave('dispatchItems', static fn(Builder $query): Builder => $query->whereDoesntHave('returnItem'));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
        ];
    }
}
