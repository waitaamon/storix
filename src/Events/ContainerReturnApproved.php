<?php

declare(strict_types=1);

namespace Storix\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Storix\Models\ContainerReturn;

final class ContainerReturnApproved implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public ContainerReturn $containerReturn) {}
}
