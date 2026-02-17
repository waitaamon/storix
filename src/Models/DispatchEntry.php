<?php

declare(strict_types=1);

namespace Storix\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Config;
use Storix\Database\Factories\DispatchEntryFactory;
use Storix\Enums\ReturnCondition;
use Storix\Support\TableNames;

#[UseFactory(DispatchEntryFactory::class)]
final class DispatchEntry extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['dispatch_id', 'container_id', 'received_by', 'return_date', 'return_condition', 'return_note'];

    /**
     * Get the container that this entry belongs to.
     *
     * @return BelongsTo<Container, self>
     */
    public function container(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.container', 'Storix\\Models\\Container');

        return $this->belongsTo($model, 'container_id');
    }

    /**
     * Get the dispatch that this entry belongs to.
     *
     * @return BelongsTo<Dispatch, self>
     */
    public function dispatch(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.dispatch', 'Storix\\Models\\Dispatch');

        return $this->belongsTo($model, 'dispatch_id');
    }

    /**
     * Get the user that received this entry.
     *
     * @return BelongsTo<Model, self>
     */
    public function receivedBy(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.user', 'App\\Models\\User');

        return $this->belongsTo($model, 'received_by');
    }

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return TableNames::dispatchEntries();
    }

    /**
     * Get the casts for the model's attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'return_date' => 'immutable_date',
            'return_condition' => ReturnCondition::class,
        ];
    }
}
