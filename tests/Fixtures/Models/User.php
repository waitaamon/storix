<?php

declare(strict_types=1);

namespace Storix\Tests\Fixtures\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

final class User extends Authenticatable
{
    protected $table = 'users';

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'email', 'password'];
}
