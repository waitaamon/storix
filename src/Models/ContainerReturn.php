<?php

declare(strict_types=1);

namespace Storix\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Storix\Concerns\ResolvesConfiguredModels;

final class ContainerReturn extends Model
{
    use HasFactory;
    use ResolvesConfiguredModels;
    use SoftDeletes;

    protected $guarded = [];

    /** Get the table name from config. */
    public function getTable(): string
    {
        return (string) config('storix.tables.returns', 'container_returns');
    }

    /** The customer who returned containers. */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(self::configuredModel('customer_model'), 'customer_id');
    }

    /** The user who recorded this return. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(self::configuredModel('user_model'), 'user_id');
    }

    /** Individual container line items in this return. */
    public function items(): HasMany
    {
        return $this->hasMany(ContainerReturnItem::class, 'return_id');
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
