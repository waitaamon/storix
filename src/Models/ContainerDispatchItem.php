<?php

declare(strict_types=1);

namespace Storix\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Storix\Database\Factories\ContainerDispatchItemFactory;

final class ContainerDispatchItem extends Model
{
    /** @use HasFactory<ContainerDispatchItemFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['dispatch_id', 'container_id', 'notes'];

    /** Get the table name from config. */
    public function getTable(): string
    {
        return (string) config('storix.tables.dispatch_items', 'container_dispatch_items');
    }

    /**
     * The parent dispatch record.
     *
     * @return BelongsTo<ContainerDispatch, self>
     */
    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(ContainerDispatch::class, 'dispatch_id');
    }

    /**
     * The container that was dispatched.
     *
     * @return BelongsTo<Container, self>
     */
    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class, 'container_id');
    }

    /**
     * The return item linked to this dispatch item via the container.
     *
     * @return HasOne<ContainerReturnItem, self>
     */
    public function returnItem(): HasOne
    {
        return $this->hasOne(ContainerReturnItem::class, 'container_id', 'container_id');
    }

    /** Scope to dispatch items that have not yet been returned. */
    #[Scope]
    public function open(Builder $query): Builder
    {
        return $query->whereDoesntHave('returnItem');
    }
}
