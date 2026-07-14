<?php

declare(strict_types=1);

namespace Storix\Models\Transitions;

use Spatie\ModelStates\Transition;
use Storix\Events\DispatchVoided;
use Storix\Models\Dispatch;
use Storix\Models\States\DispatchVoidedState;

final class DispatchToVoidedTransition extends Transition
{
    public function __construct(private readonly Dispatch $dispatch) {}

    public function handle(): Dispatch
    {
        $this->dispatch->state = new DispatchVoidedState($this->dispatch);
        $this->dispatch->save();

        DispatchVoided::dispatch($this->dispatch);

        return $this->dispatch;
    }
}
