<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Ssch\TYPO3Rector\Set\Typo3SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/Classes',
        __DIR__ . '/Configuration',
        __DIR__ . '/Tests',
    ])
    ->withSets([
        SetList::CODE_QUALITY,
        Typo3SetList::TYPO3_14,
    ])
    // Minimum supported PHP version is 8.2 (do not apply rules requiring a newer version)
    ->withPhpSets(php82: true);

