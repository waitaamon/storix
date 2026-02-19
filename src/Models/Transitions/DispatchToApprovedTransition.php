<?php

declare(strict_types=1);

namespace Storix\Models\Transitions;

use Spatie\ModelStates\Transition;
use Storix\Events\DispatchApproved;
use Storix\Models\Dispatch;
use Storix\Models\States\DispatchApprovedState;

final class DispatchToApprovedTransition extends Transition
{
    public function __construct(private readonly Dispatch $dispatch) {}

    public function handle(): Dispatch
    {
        $this->dispatch->state = new DispatchApprovedState($this->dispatch);
        $this->dispatch->save();

        DispatchApproved::dispatch($this->dispatch);

        return $this->dispatch;
    }
}
