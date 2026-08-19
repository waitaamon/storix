<?php

declare(strict_types=1);

namespace Storix\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'account_category_id'])]
#[Table(name: 'customers')]
final class CustomerWithFinancialBalance extends Model
{
    /** @return BelongsTo<CustomerCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CustomerCategory::class, 'account_category_id');
    }

    /** @return Attribute<float, never> */
    protected function balance(): Attribute
    {
        return Attribute::get(static fn (): float => 78_300.0);
    }
}
