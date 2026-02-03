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

final class ContainerDispatchItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    /** Get the table name from config. */
    public function getTable(): string
    {
        return (string) config('storix.tables.dispatch_items', 'container_dispatch_items');
    }

    /** The parent dispatch record. */
    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(ContainerDispatch::class, 'dispatch_id');
    }

    /** The container that was dispatched. */
    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class, 'container_id');
    }

    /** The corresponding return item, if the container has been returned. */
    public function returnItem(): HasOne
    {
        return $this->hasOne(ContainerReturnItem::class, 'dispatch_item_id');
    }

    /** Scope to dispatch items that have not yet been returned. */
    #[Scope]
    public function open(Builder $query): Builder
    {
        return $query->whereDoesntHave('returnItem');
    }
}
