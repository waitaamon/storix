<?php

declare(strict_types=1);

namespace Storix\Models;

use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Config;
use Spatie\ModelStates\HasStates;
use Spatie\ModelStates\HasStatesContract;
use Storix\Database\Factories\DispatchFactory;
use Storix\Models\States\DispatchState;
use Storix\Support\TableNames;

#[UseFactory(DispatchFactory::class)]
final class Dispatch extends Model implements HasStatesContract
{
    use HasFactory, HasStates, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = ['customer_id', 'delivery_note_id', 'dispatched_by', 'dispatched_at', 'dispatched_note', 'state'];

    /**
     * Get the customer that the items were dispatched to.
     *
     * @return BelongsTo<Model, self>
     */
    public function customer(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.customer', 'App\\Models\\Accounts\\Account');

        return $this->belongsTo($model, 'customer_id');
    }

    /**
     * Get the delivery note associated with the dispatch.
     *
     * @return BelongsTo<Model, self>
     */
    public function deliveryNote(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.delivery_note', 'App\\Models\\Sales\\DeliveryNote');

        return $this->belongsTo($model, 'delivery_note_id');
    }

    /**
     * Get the user that dispatched the items.
     *
     * @return BelongsTo<Model, self>
     */
    public function dispatchedBy(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.user', 'App\\Models\\User');

        return $this->belongsTo($model, 'dispatched_by');
    }

    /**
     * Get all the entries for the dispatch.
     *
     * @return HasMany<Model, self>
     */
    public function entries(): HasMany
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.dispatch_entry', 'Storix\\Models\\DispatchEntry');

        return $this->hasMany($model, 'dispatch_id');
    }

    /**
     * Get all the containers for the dispatch.
     *
     * @return BelongsToMany<Model, self>
     */
    public function containers(): BelongsToMany
    {
        /** @var class-string<Model> $model */
        $model = Config::string('storix.models.container', 'Storix\\Models\\Container');

        return $this->belongsToMany($model, TableNames::dispatchEntries())
            ->using(Config::string('storix.models.dispatch_entry', 'Storix\\Models\\DispatchEntry'))
            ->withPivot('received_by', 'return_date', 'return_condition', 'return_note')
            ->withTimestamps();
    }

    /**
     * Get the name of the table associated with the model.
     */
    public function getTable(): string
    {
        return TableNames::dispatches();
    }

    protected static function booted(): void
    {
        self::creating(function (self $dispatch): void {
            $dispatch->dispatched_by = $dispatch->dispatched_by ?? (auth()->check() ? auth()->id() : null);
            $dispatch->dispatched_at = $dispatch->dispatched_at ?? today();
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'dispatched_at' => 'immutable_date',
            'metadata' => 'array',
            'state' => DispatchState::class,
        ];
    }
}
