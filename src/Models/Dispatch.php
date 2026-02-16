<?php

declare(strict_types=1);

namespace Storix\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Config;
use Storix\Database\Factories\DispatchFactory;
use Storix\Enums\DispatchStatus;
use Storix\Enums\ReturnCondition;
use Storix\Support\TableNames;

final class Dispatch extends Model
{
    /** @use HasFactory<DispatchFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id', 'container_id', 'dispatched_by', 'delivery_note', 'dispatched_at', 'dispatched_note',
        'received_by', 'return_date', 'return_condition', 'return_note', 'metadata',
    ];

    /** @return BelongsTo<Container, self> */
    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    /** @return BelongsTo<Model, self> */
    public function customer(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.customer_model', 'App\\Models\\Accounts\\Account');

        return $this->belongsTo($model, 'customer_id');
    }

    /** @return BelongsTo<Model, self> */
    public function dispatchedBy(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.user_model', 'App\\Models\\User');

        return $this->belongsTo($model, 'dispatched_by');
    }

    /** @return BelongsTo<Model, self> */
    public function receivedBy(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.user_model', 'App\\Models\\User');

        return $this->belongsTo($model, 'received_by');
    }

    public function getTable(): string
    {
        return TableNames::dispatches();
    }

    protected static function newFactory(): DispatchFactory
    {
        return DispatchFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'dispatched_at' => 'immutable_datetime',
            'return_date' => 'immutable_datetime',
            'return_condition' => ReturnCondition::class,
            'metadata' => 'array',
        ];
    }

    /** @return Attribute<DispatchStatus, never> */
    protected function status(): Attribute
    {
        return Attribute::get(fn (): DispatchStatus => DispatchStatus::fromReturnCondition($this->return_condition));
    }
}
