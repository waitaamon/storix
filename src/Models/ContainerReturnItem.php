<?php

declare(strict_types=1);

namespace Storix\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Storix\Enums\ContainerConditionStatus;

final class ContainerReturnItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    /** Get the table name from config. */
    public function getTable(): string
    {
        return (string) config('storix.tables.return_items', 'container_return_items');
    }

    /** The parent return record. */
    public function return(): BelongsTo
    {
        return $this->belongsTo(ContainerReturn::class, 'return_id');
    }

    /** The container that was returned. */
    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class, 'container_id');
    }

    /** The original dispatch item this return fulfils. */
    public function dispatchItem(): BelongsTo
    {
        return $this->belongsTo(ContainerDispatchItem::class, 'dispatch_item_id');
    }

    /** @return array<string, string|class-string> */
    protected function casts(): array
    {
        return [
            'returned_at' => 'datetime',
            'condition_status' => ContainerConditionStatus::class,
        ];
    }
}
