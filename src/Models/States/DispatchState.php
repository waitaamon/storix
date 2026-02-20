<?php

declare(strict_types=1);

namespace Storix\Models\States;

use Override;
use Spatie\ModelStates\Exceptions\InvalidConfig;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;
use Storix\Models\Transitions\DispatchToApprovedTransition;
use Storix\Models\Transitions\DispatchToDraftTransition;

abstract class DispatchState extends State
{
    /**
     * @throws InvalidConfig
     */
    #[Override]
    final public static function config(): StateConfig
    {
        return parent::config()
            ->default(defaultStateClass: DispatchDraftState::class)
            ->allowTransition(from: DispatchDraftState::class, to: DispatchApprovedState::class, transition: DispatchToApprovedTransition::class)
            ->allowTransition(from: DispatchApprovedState::class, to: DispatchDraftState::class, transition: DispatchToDraftTransition::class);
    }
}
