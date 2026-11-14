<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;

return RectorConfig::configure()
    ->withPaths(
        [
            __DIR__ . '/config',
            __DIR__ . '/public',
            __DIR__ . '/src',
            __DIR__ . '/tests',
        ]
    )
    ->withPreparedSets(deadCode: true, codeQuality: true)
    ->withPhpSets(php85: true)
    ->withAttributesSets(symfony: true, doctrine: true)
    ->withComposerBased(doctrine: true, symfony: true)
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withRules(
        [
            AddVoidReturnTypeWhereNoReturnRector::class,
        ]
    );
