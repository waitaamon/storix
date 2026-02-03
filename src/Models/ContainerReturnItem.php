<?php

declare(strict_types=1);

namespace Storix\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Storix\Database\Factories\ContainerReturnItemFactory;
use Storix\Enums\ContainerConditionStatus;

final class ContainerReturnItem extends Model
{
    /** @use HasFactory<ContainerReturnItemFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['return_id', 'container_id', 'condition_status', 'notes'];

    /** Get the table name from config. */
    public function getTable(): string
    {
        return (string)config('storix.tables.return_items', 'container_return_items');
    }

    /**
     * The parent return record.
     *
     * @return BelongsTo<ContainerReturn, self>
     */
    public function return(): BelongsTo
    {
        return $this->belongsTo(ContainerReturn::class, 'return_id');
    }

    /**
     * The container that was returned.
     *
     * @return BelongsTo<Container, self>
     */
    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class, 'container_id');
    }


    /** @return array<string, string|class-string> */
    protected function casts(): array
    {
        return [
            'condition_status' => ContainerConditionStatus::class,
        ];
    }
}
