<?php

declare(strict_types=1);

namespace Storix\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

final class DeliveryNote extends Model
{
    protected $table = 'delivery_notes';

    /**
     * @var list<string>
     */
    protected $fillable = ['name'];
}
