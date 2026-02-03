<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ContainerReturnItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    public function getTable(): string
    {
        return (string) config('container-movement.tables.return_items', 'container_return_items');
    }

    protected function casts(): array
    {
        return [
            'returned_at' => 'datetime',
        ];
    }

    public function return(): BelongsTo
    {
        return $this->belongsTo(ContainerReturn::class, 'return_id');
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class, 'container_id');
    }

    public function dispatchItem(): BelongsTo
    {
        return $this->belongsTo(ContainerDispatchItem::class, 'dispatch_item_id');
    }
}
