<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = Finder::create()
    ->in(array_filter(
        [__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/bin'],
        is_dir(...),
    ))
    ->notPath('#^(var|vendor)/#')
    ->ignoreDotFiles(false)
    ->name('*.php')
    ->append([
        __DIR__ . '/bin/check-coverage',
        __DIR__ . '/bin/check-branch-coverage',
        __DIR__ . '/bin/build-flex-endpoint',
        __DIR__ . '/.php-cs-fixer.dist.php',
        __DIR__ . '/composer-unused.php',
        __DIR__ . '/rector.php',
    ]);

// Every PHP source file carries the same short licence header (OpenSSF: copyright_per_file, license_per_file).
$header = <<<'HEADER'
    This file is part of thebeardedbear/urssaf-tiers-prestations.

    Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors

    SPDX-License-Identifier: MIT
    HEADER;

return (new Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__ . '/var/.php-cs-fixer.cache')
    ->setRules([
        '@PER-CS2.0' => true,
        '@PER-CS2.0:risky' => true,
        '@PHP82Migration' => true,
        '@PHP82Migration:risky' => true,
        '@PHPUnit100Migration:risky' => true,
        'declare_strict_types' => true,
        'header_comment' => ['header' => $header, 'comment_type' => 'comment', 'location' => 'after_declare_strict', 'separate' => 'both'],
        'final_class' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'global_namespace_import' => ['import_classes' => true, 'import_constants' => false, 'import_functions' => false],
        'native_function_invocation' => ['include' => ['@compiler_optimized'], 'scope' => 'namespaced'],
        'phpdoc_align' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        'void_return' => true,
    ])
    ->setFinder($finder);
