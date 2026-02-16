<?php

declare(strict_types=1);

namespace Storix\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

final class Customer extends Model
{
    protected $table = 'customers';

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'email'];
}
