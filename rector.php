<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths(array_filter(
        [__DIR__ . '/src', __DIR__ . '/tests'],
        is_dir(...),
    ))
    ->withCache(__DIR__ . '/var/rector')
    ->withPhpSets(php82: true)
    ->withPreparedSets(
        codeQuality: true,
        deadCode: true,
        typeDeclarations: true,
        earlyReturn: true,
        phpunitCodeQuality: true,
    )
    // Picks the PHPUnit 11.0 migration sets from the installed phpunit/phpunit version.
    ->withComposerBased(phpunit: true)
    // Private static helpers (pure assertions/factories) are intentionally static in value objects.
    ->withSkip([
        LocallyCalledStaticMethodToNonStaticRector::class,
        // Tests use static::assert*() (Mago assertion-style + PHPStan strict rules forbid dynamic static calls).
        \Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector::class,
        // Keep static:: (late static binding) in tests, as required by Mago assertion-style.
        \Rector\CodeQuality\Rector\Class_\ConvertStaticToSelfRector::class => ['tests'],
    ])
    ->withImportNames(removeUnusedImports: true);
