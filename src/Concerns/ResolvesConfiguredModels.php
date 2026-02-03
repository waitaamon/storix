<?php

declare(strict_types=1);

namespace Storix\ContainerMovement\Concerns;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

trait ResolvesConfiguredModels
{
    /**
     * @return class-string<Model>
     */
    protected static function configuredModel(string $configKey): string
    {
        $value = config('container-movement.'.$configKey);

        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException(sprintf('Invalid model configured for key [%s].', $configKey));
        }

        /** @var class-string<Model> $value */
        return $value;
    }
}
