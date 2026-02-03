<?php

declare(strict_types=1);

namespace Storix\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Storix\Models\ContainerReturn;

/** Fired after containers are returned from a customer. */
final class ContainerReturned
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public ContainerReturn $return) {}
}
