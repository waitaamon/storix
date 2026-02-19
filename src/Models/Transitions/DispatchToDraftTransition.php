<?php

declare(strict_types=1);

namespace Storix\Models\Transitions;

use Spatie\ModelStates\Transition;
use Storix\Events\DispatchApproved;
use Storix\Events\DispatchDrafted;
use Storix\Models\Dispatch;
use Storix\Models\States\DispatchApprovedState;
use Storix\Models\States\DispatchDraftState;

final class DispatchToDraftTransition extends Transition
{
    public function __construct(private readonly Dispatch $dispatch) {}

    public function handle(): Dispatch
    {
        $this->dispatch->state = new DispatchDraftState($this->dispatch);
        $this->dispatch->save();

        DispatchDrafted::dispatch($this->dispatch);

        return $this->dispatch;
    }
}
