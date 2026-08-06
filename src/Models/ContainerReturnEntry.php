<?php

declare(strict_types=1);

namespace Storix\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Config;
use Override;
use Storix\Database\Factories\ContainerReturnEntryFactory;
use Storix\Enums\ReturnCondition;
use Storix\Support\TableNames;

/**
 * @property int $id
 * @property int|string $container_return_id
 * @property int|string $container_id
 * @property int|string|null $dispatch_entry_id
 * @property ReturnCondition $return_condition
 * @property string|null $note
 * @property bool $cross_return
 * @property-read ContainerReturn $containerReturn
 * @property-read Container $container
 * @property-read DispatchEntry|null $dispatchEntry
 */
#[UseFactory(ContainerReturnEntryFactory::class)]
#[Fillable([
    'container_return_id',
    'container_id',
    'return_condition',
    'note',
])]
final class ContainerReturnEntry extends Model
{
    /** @use HasFactory<ContainerReturnEntryFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Model, $this>
     */
    public function containerReturn(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.container_return', ContainerReturn::class);

        return $this->belongsTo($model, 'container_return_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function container(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.container', Container::class);

        return $this->belongsTo($model, 'container_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function dispatchEntry(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.dispatch_entry', DispatchEntry::class);

        return $this->belongsTo($model, 'dispatch_entry_id');
    }

    #[Override]
    public function getTable(): string
    {
        return TableNames::containerReturnEntries();
    }

    #[Override]
    protected static function booted(): void
    {
        self::creating(function (self $entry): void {
            $entry->cross_return ??= false;
        });
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'return_condition' => ReturnCondition::class,
            'cross_return' => 'boolean',
        ];
    }
}
