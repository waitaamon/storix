<?php

declare(strict_types=1);

namespace WaitAmon\Storix\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use WaitAmon\Storix\Database\Factories\ContainerFactory;
use WaitAmon\Storix\Support\TableNames;

final class Container extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'serial',
        'is_active',
        'description',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
            'metadata' => 'array',
        ];
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(Dispatch::class);
    }

    public function getTable(): string
    {
        return TableNames::containers();
    }

    protected static function newFactory(): ContainerFactory
    {
        return ContainerFactory::new();
    }
}
