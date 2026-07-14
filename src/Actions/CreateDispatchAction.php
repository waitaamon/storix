<?php

declare(strict_types=1);

namespace Storix\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Storix\Data\CreateDispatchData;
use Storix\Models\Dispatch;
use Throwable;

final readonly class CreateDispatchAction
{
    public function __construct(
        private AttachContainersToDispatchAction $attachContainers,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(CreateDispatchData $data): Dispatch
    {
        return DB::transaction(function () use ($data): Dispatch {
            $dispatch = Dispatch::query()->create([
                'delivery_note_id' => $data->deliveryNoteId,
                'dispatched_by' => $data->dispatchedBy,
                'dispatched_at' => $data->dispatchedAt ? CarbonImmutable::parse($data->dispatchedAt) : now(),
                'dispatch_note' => $data->dispatchNote,
            ]);

            if ($data->containerIds !== []) {
                $this->attachContainers->handle($dispatch, $data->containerIds);
            }

            return $dispatch->refresh();
        });
    }
}
