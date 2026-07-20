<?php

declare(strict_types=1);

namespace Storix\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Storix\Data\CreateDispatchData;

final readonly class GenerateDraftDispatchRequested implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public CreateDispatchData $data) {}
}
