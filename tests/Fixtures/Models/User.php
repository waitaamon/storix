<?php

declare(strict_types=1);

namespace WaitAmon\Storix\Tests\Fixtures\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class User extends Authenticatable
{
    use HasUuids;

    protected $table = 'users';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'email', 'password'];
}
