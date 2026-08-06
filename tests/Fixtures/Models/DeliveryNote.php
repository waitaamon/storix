<?php

declare(strict_types=1);

namespace Storix\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int|string $customer_id
 */
#[Fillable(['name', 'customer_id'])]
#[Table(name: 'delivery_notes')]
final class DeliveryNote extends Model
{
    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    #[Override]
    protected static function booted(): void
    {
        self::creating(function (self $deliveryNote): void {
            if ($deliveryNote->getAttribute('customer_id') === null) {
                $deliveryNote->setAttribute(
                    'customer_id',
                    Customer::query()->create(['name' => 'Test Customer'])->getKey(),
                );
            }
        });
    }
}
