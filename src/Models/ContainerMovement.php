<?php

declare(strict_types=1);

namespace Storix\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Config;
use LogicException;
use Override;
use Storix\Enums\ContainerMovementType;
use Storix\Support\TableNames;

/**
 * @property string $id
 * @property int|string $container_id
 * @property CarbonImmutable $movement_date
 * @property int|string $customer_id
 * @property ContainerMovementType $document_type
 * @property int|string $document_id
 * @property string $document_code
 * @property bool|null $cross_return
 * @property-read Model $container
 * @property-read Model $customer
 */
#[Guarded('*')]
#[Table(keyType: 'string')]
#[WithoutIncrementing]
#[WithoutTimestamps]
final class ContainerMovement extends Model
{
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
    public function customer(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.customer');

        return $this->belongsTo($model, 'customer_id');
    }

    #[Override]
    public function getTable(): string
    {
        return TableNames::containerMovements();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    #[Override]
    public function save(array $options = []): never
    {
        throw new LogicException('Container movements are read-only database view records.');
    }

    #[Override]
    public function delete(): never
    {
        throw new LogicException('Container movements are read-only database view records.');
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'movement_date' => 'immutable_datetime',
            'document_type' => ContainerMovementType::class,
            'cross_return' => 'boolean',
        ];
    }
}
