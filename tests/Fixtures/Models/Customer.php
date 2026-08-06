<?php

declare(strict_types=1);

namespace Storix\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

#[Fillable(['name', 'account_category_id'])]
#[Table(name: 'customers')]
final class Customer extends Model
{
    /** @return BelongsTo<CustomerCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CustomerCategory::class, 'account_category_id');
    }

    #[Override]
    protected static function booted(): void
    {
        self::creating(function (self $customer): void {
            if ($customer->getAttribute('account_category_id') !== null) {
                return;
            }

            $customer->setAttribute(
                'account_category_id',
                CustomerCategory::query()->firstOrCreate(
                    ['slug' => 'accounts-receivable'],
                    ['name' => 'Accounts Receivable'],
                )->getKey(),
            );
        });
    }
}
