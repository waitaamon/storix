<?php

declare(strict_types=1);

namespace Storix\Concerns;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

trait ResolvesConfiguredModels
{
    /**
     * Resolve a model class from the storix config.
     *
     * @return class-string<Model>
     *
     * @throws InvalidArgumentException
     */
    protected static function configuredModel(string $configKey): string
    {
        $value = config('storix.'.$configKey);

        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException(sprintf('Invalid model configured for key [%s].', $configKey));
        }

        /** @var class-string<Model> $value */
        return $value;
    }
}
