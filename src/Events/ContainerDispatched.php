<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Storix\ContainerMovement\Models\ContainerDispatch;

final class ContainerDispatched
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public ContainerDispatch $dispatch) {}
}
