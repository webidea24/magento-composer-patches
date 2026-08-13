#!/usr/bin/env php
<?php

declare(strict_types=1);

use Webidea24\MagentoComposerPatches\Build\PatchFileBuilder;

require dirname(__DIR__) . '/vendor/autoload.php';

$packageRoot = dirname(__DIR__);
$outputDirectory = $packageRoot . '/.build/patches';

foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--output=')) {
        $outputDirectory = substr($argument, strlen('--output='));
        continue;
    }

    fwrite(STDERR, sprintf("Unknown argument: %s\n", $argument));
    exit(1);
}

if ($outputDirectory === '') {
    fwrite(STDERR, "The build output directory must not be empty.\n");
    exit(1);
}

$writtenFiles = (new PatchFileBuilder(
    logger: static function (string $message): void {
        fwrite(STDOUT, $message . PHP_EOL);
    },
))->build($packageRoot, $outputDirectory);
fwrite(STDOUT, sprintf("Built %d package patch%s in %s\n", $writtenFiles, $writtenFiles === 1 ? '' : 'es', $outputDirectory));
