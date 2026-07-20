<?php

declare(strict_types=1);

namespace Storix\Listeners;

use Storix\Actions\CreateDispatchAction;
use Storix\Events\GenerateDraftDispatchRequested;
use Throwable;

final readonly class GenerateDraftDispatch
{
    public function __construct(
        private CreateDispatchAction $createDispatch,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(GenerateDraftDispatchRequested $event): void
    {
        $this->createDispatch->handle($event->data);
    }
}
