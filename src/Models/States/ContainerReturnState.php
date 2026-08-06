<?php

declare(strict_types=1);

namespace Storix\Models\States;

use Override;
use Spatie\ModelStates\Exceptions\InvalidConfig;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;
use Storix\Models\ContainerReturn;
use Storix\Models\Transitions\ContainerReturnToApprovedTransition;
use Storix\Models\Transitions\ContainerReturnToDraftTransition;
use Storix\Models\Transitions\ContainerReturnToSubmittedTransition;

/**
 * @extends State<ContainerReturn>
 */
abstract class ContainerReturnState extends State
{
    /**
     * @throws InvalidConfig
     */
    #[Override]
    final public static function config(): StateConfig
    {
        return parent::config()
            ->default(ContainerReturnDraftState::class)
            ->allowTransition(
                from: ContainerReturnDraftState::class,
                to: ContainerReturnSubmittedState::class,
                transition: ContainerReturnToSubmittedTransition::class,
            )
            ->allowTransition(
                from: ContainerReturnSubmittedState::class,
                to: ContainerReturnDraftState::class,
                transition: ContainerReturnToDraftTransition::class,
            )
            ->allowTransition(
                from: ContainerReturnSubmittedState::class,
                to: ContainerReturnApprovedState::class,
                transition: ContainerReturnToApprovedTransition::class,
            );
    }
}
