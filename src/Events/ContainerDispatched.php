<?php

declare(strict_types=1);

namespace Storix\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Storix\Models\ContainerDispatch;

/** Fired after containers are dispatched to a customer. */
final class ContainerDispatched
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public ContainerDispatch $dispatch) {}
}
