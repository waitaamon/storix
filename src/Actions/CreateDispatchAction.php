<?php

declare(strict_types=1);

namespace Storix\Actions;

use Carbon\CarbonImmutable;
use DomainException;
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
            $idempotencyKey = $this->normalizeIdempotencyKey($data->idempotencyKey);
            $values = [
                'delivery_note_id' => $data->deliveryNoteId,
                'dispatched_by' => $data->dispatchedBy,
                'dispatched_at' => $data->dispatchedAt ? CarbonImmutable::parse($data->dispatchedAt) : now(),
                'dispatch_note' => $data->dispatchNote,
                'metadata' => $data->metadata === [] ? null : $data->metadata,
            ];

            if ($idempotencyKey === null) {
                $dispatch = Dispatch::query()->create($values);
            } else {
                $fingerprint = $this->idempotencyFingerprint($data);
                $values['idempotency_fingerprint'] = $fingerprint;

                $dispatch = Dispatch::withTrashed()->createOrFirst(
                    ['idempotency_key' => $idempotencyKey],
                    $values,
                );

                if (! $dispatch->wasRecentlyCreated) {
                    $this->ensureIdempotencyKeyMatchesRequest($dispatch, $fingerprint);

                    return $dispatch->refresh();
                }
            }

            if ($data->containerIds !== []) {
                $this->attachContainers->handle($dispatch, $data->containerIds);
            }

            return $dispatch->refresh();
        });
    }

    private function normalizeIdempotencyKey(?string $idempotencyKey): ?string
    {
        if (blank($idempotencyKey)) {
            return null;
        }

        $idempotencyKey = str($idempotencyKey)->trim()->toString();

        if (mb_strlen($idempotencyKey) > 255) {
            throw new DomainException('The dispatch idempotency key may not exceed 255 characters.');
        }

        return $idempotencyKey;
    }

    private function ensureIdempotencyKeyMatchesRequest(Dispatch $dispatch, string $fingerprint): void
    {
        if (! hash_equals((string) $dispatch->idempotency_fingerprint, $fingerprint)) {
            throw new DomainException(
                "The dispatch idempotency key [{$dispatch->idempotency_key}] has already been used for another request.",
            );
        }
    }

    private function idempotencyFingerprint(CreateDispatchData $data): string
    {
        $containerIds = array_values(array_unique(array_filter(
            $data->containerIds,
            filled(...),
        )));
        $containerIds = array_map(static fn (int|string $id): string => (string) $id, $containerIds);
        sort($containerIds);

        $payload = [
            'delivery_note_id' => (string) $data->deliveryNoteId,
            'dispatched_by' => (string) $data->dispatchedBy,
            'dispatched_at' => $data->dispatchedAt === null
                ? null
                : CarbonImmutable::parse($data->dispatchedAt)->utc()->toISOString(),
            'dispatch_note' => $data->dispatchNote,
            'container_ids' => $containerIds,
            'metadata' => $this->normalizeForFingerprint($data->metadata),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function normalizeForFingerprint(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item): mixed => $this->normalizeForFingerprint($item),
                $value,
            );
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalizeForFingerprint($item);
        }

        return $value;
    }
}
