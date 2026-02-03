<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Storix\ContainerMovement\Database\Factories\ContainerFactory;

final class Container extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
        ];
    }

    public function getTable(): string
    {
        return (string) config('container-movement.tables.containers', 'containers');
    }

    public function dispatchItems(): HasMany
    {
        return $this->hasMany(ContainerDispatchItem::class, 'container_id');
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(ContainerReturnItem::class, 'container_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeWithCustomers(Builder $query): Builder
    {
        return $query->whereHas('dispatchItems', static fn (Builder $dispatchItems): Builder => $dispatchItems
            ->whereDoesntHave('returnItem')
        );
    }

    public function scopeAvailableForDispatch(Builder $query): Builder
    {
        return $query
            ->active()
            ->whereDoesntHave('dispatchItems', static fn (Builder $dispatchItems): Builder => $dispatchItems
                ->whereDoesntHave('returnItem')
            );
    }

    protected static function newFactory(): Factory
    {
        return ContainerFactory::new();
    }
}
