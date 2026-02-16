<?php

declare(strict_types=1);

namespace Storix\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Storix\Database\Factories\ContainerFactory;
use Storix\Support\TableNames;

final class Container extends Model
{
    /** @use HasFactory<ContainerFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'serial', 'is_active', 'description', 'metadata'];

    /** @return HasMany<Dispatch> */
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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
