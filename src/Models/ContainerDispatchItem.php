<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\Models;

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

    public function getTable(): string
    {
        return (string) config('container-movement.tables.dispatch_items', 'container_dispatch_items');
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(ContainerDispatch::class, 'dispatch_id');
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class, 'container_id');
    }

    public function returnItem(): HasOne
    {
        return $this->hasOne(ContainerReturnItem::class, 'dispatch_item_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereDoesntHave('returnItem');
    }
}
