<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Exception\Configuration\InvalidConfigurationException;
use RectorLaravel\Rector\StaticCall\EloquentMagicMethodToQueryBuilderRector;
use RectorLaravel\Set\LaravelSetProvider;

try {
    return RectorConfig::configure()
        ->withPaths([
            __DIR__.'/src',
            __DIR__.'/tests',
        ])
        ->withRules([
            EloquentMagicMethodToQueryBuilderRector::class,
        ])
        ->withPreparedSets(
            deadCode: true,
            codeQuality: true,
            typeDeclarations: true,
            privatization: true,
            earlyReturn: true,
        )
        ->withImportNames(removeUnusedImports: true)
        ->withSetProviders(LaravelSetProvider::class)
        ->withComposerBased(laravel: true)
        ->withPhpSets();
} catch (InvalidConfigurationException $e) {
    Log::error($e->getMessage());
}
