<?php

declare(strict_types=1);

namespace Storix\Models\Transitions;

use Spatie\ModelStates\Transition;
use Storix\Models\ContainerReturn;
use Storix\Models\States\ContainerReturnDraftState;

final class ContainerReturnToDraftTransition extends Transition
{
    public function __construct(private readonly ContainerReturn $containerReturn) {}

    public function handle(): ContainerReturn
    {
        $this->containerReturn->state = new ContainerReturnDraftState($this->containerReturn);
        $this->containerReturn->save();

        return $this->containerReturn;
    }
}
