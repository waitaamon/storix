<?php

declare(strict_types=1);

namespace Storix\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Storix\Concerns\ResolvesConfiguredModels;
use Storix\Database\Factories\ContainerDispatchFactory;

final class ContainerDispatch extends Model
{
    /** @use HasFactory<ContainerDispatchFactory> */
    use HasFactory, ResolvesConfiguredModels, SoftDeletes;

    protected $fillable = ['customer_id', 'user_id', 'delivery_note_code', 'transaction_date', 'notes', 'attachments'];

    /** Get the table name from config. */
    public function getTable(): string
    {
        return (string) config('storix.tables.dispatches', 'container_dispatches');
    }

    /**
     * The customer this dispatch was sent to.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(self::configuredModel('customer_model'), 'customer_id');
    }

    /** The user who created this dispatch. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(self::configuredModel('user_model'), 'user_id');
    }

    /**
     * Individual container line items in this dispatch.
     *
     * @return HasMany<ContainerDispatchItem, self>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ContainerDispatchItem::class, 'dispatch_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'attachments' => 'array',
        ];
    }
}
