<?php

declare(strict_types=1);

namespace WaitAmon\Storix\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use WaitAmon\Storix\Database\Factories\DispatchFactory;
use WaitAmon\Storix\Enums\DispatchStatus;
use WaitAmon\Storix\Enums\ReturnCondition;
use WaitAmon\Storix\Support\TableNames;

final class Dispatch extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'container_id',
        'dispatched_by',
        'delivery_note',
        'received_by',
        'dispatched_at',
        'dispatched_note',
        'return_date',
        'return_condition',
        'return_note',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dispatched_at' => 'immutable_datetime',
            'return_date' => 'immutable_datetime',
            'return_condition' => ReturnCondition::class,
            'metadata' => 'array',
        ];
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function getTable(): string
    {
        return TableNames::dispatches();
    }

    public function customer(): BelongsTo
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
        $model = (string) config('storix.customer_model', 'App\\Models\\Customer');

        return $this->belongsTo($model, 'customer_id');
    }

    public function dispatchedBy(): BelongsTo
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
        $model = (string) config('storix.user_model', 'App\\Models\\User');

        return $this->belongsTo($model, 'dispatched_by');
    }

    public function receivedBy(): BelongsTo
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
        $model = (string) config('storix.user_model', 'App\\Models\\User');

        return $this->belongsTo($model, 'received_by');
    }

    /**
     * @return Attribute<DispatchStatus, never>
     */
    protected function status(): Attribute
    {
        return Attribute::get(fn (): DispatchStatus => DispatchStatus::fromReturnCondition($this->return_condition));
    }

    protected static function newFactory(): DispatchFactory
    {
        return DispatchFactory::new();
    }
}
