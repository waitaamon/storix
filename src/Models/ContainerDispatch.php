<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Storix\ContainerMovement\Concerns\ResolvesConfiguredModels;

final class ContainerDispatch extends Model
{
    use HasFactory;
    use ResolvesConfiguredModels;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'attachments' => 'array',
        ];
    }

    public function getTable(): string
    {
        return (string) config('container-movement.tables.dispatches', 'container_dispatches');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(static::configuredModel('customer_model'), 'customer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(static::configuredModel('user_model'), 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContainerDispatchItem::class, 'dispatch_id');
    }
}
