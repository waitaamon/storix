<?php

declare(strict_types=1);

use Storix\Enums\ContainerMovementType;

it('provides filament labels and colors for movement documents', function (): void {
    expect(ContainerMovementType::Dispatch->getLabel())->toBe('Dispatch')
        ->and(ContainerMovementType::Dispatch->getColor())->toBe('info')
        ->and(ContainerMovementType::Return->getLabel())->toBe('Return')
        ->and(ContainerMovementType::Return->getColor())->toBe('success');
});
