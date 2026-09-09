<?php

declare(strict_types=1);

namespace Webidea24\MagentoComposerPatches\Configuration;

use RuntimeException;

/**
 * Merges this package's remote vendor patches into a Composer patches file.
 */
final class ComposerPatchMap
{
    public const DESCRIPTION_PREFIX = '[webidea24/magento-composer-patches] ';

    /**
     * @param list<array{description: string, package: string, path: string}> $patches
     */
    public function replaceGeneratedPatchUrls(
        string $patchesFile,
        string $baseUrl,
        array $patches,
        bool $mergeIntoComposerExtra = false
    ): int {
        $configuration = $this->readConfiguration($patchesFile);
        $patchMap = $this->readPatchMap($configuration, $patchesFile, $mergeIntoComposerExtra);
        $patchMap = $this->removeGeneratedEntriesFromPatchMap($patchMap, false)['patchMap'];

        $generatedPatches = $this->createRemotePatchUrls($baseUrl, $patches);

        foreach ($generatedPatches as $packageName => $packagePatches) {
            if (!isset($patchMap[$packageName])) {
                $patchMap[$packageName] = [];
            }
            foreach ($packagePatches as $description => $url) {
                $patchMap[$packageName][$description] = $url;
            }
        }
        $patchMap = $this->removeEmptyPackages($patchMap);

        $this->writePatchMap($configuration, $patchesFile, $mergeIntoComposerExtra, $patchMap);
        $this->writeConfiguration($patchesFile, $configuration);

        return array_sum(array_map('count', $generatedPatches));
    }

    /**
     * @param list<array{description: string, package: string, path: string}> $patches
     * @return array<string, array<string, string>>
     */
    private function createRemotePatchUrls(string $baseUrl, array $patches): array
    {
        $generatedPatches = [];
        $baseUrl = rtrim($baseUrl, '/');

        foreach ($patches as $patch) {
            $generatedPatches[$patch['package']][self::DESCRIPTION_PREFIX . $patch['description']] = $baseUrl
                . '/' . ltrim($patch['path'], '/');
        }

        foreach ($generatedPatches as &$packagePatches) {
            uksort($packagePatches, 'strnatcmp');
        }
        unset($packagePatches);

        return $generatedPatches;
    }

    /**
     * @return array<string, mixed>
     */
    private function readConfiguration(string $patchesFile): array
    {
        if (!is_file($patchesFile)) {
            return [];
        }

        $contents = file_get_contents($patchesFile);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Cannot read Composer patches file: %s', $patchesFile));
        }

        $configuration = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(sprintf('Cannot parse Composer patches file %s: %s', $patchesFile, json_last_error_msg()));
        }

        if (!is_array($configuration) || $this->isList($configuration)) {
            throw new RuntimeException(sprintf('Composer patches file must contain a JSON object: %s', $patchesFile));
        }

        $normalizedConfiguration = [];
        foreach ($configuration as $key => $value) {
            if (!is_string($key)) {
                throw new RuntimeException(sprintf('Composer patches file must contain a JSON object: %s', $patchesFile));
            }

            $normalizedConfiguration[$key] = $value;
        }

        return $normalizedConfiguration;
    }

    /**
     * @param array<string, mixed> $configuration
     * @return array<string, array<string, string>>
     */
    private function readPatchMap(array $configuration, string $patchesFile, bool $fromComposerExtra): array
    {
        if ($fromComposerExtra) {
            $extra = $configuration['extra'] ?? [];
            if (!is_array($extra)) {
                throw new RuntimeException(sprintf('The extra property in %s must be an object.', $patchesFile));
            }

            $patches = $extra['patches'] ?? [];
        } else {
            $patches = $configuration['patches'] ?? [];
        }

        if (!is_array($patches)) {
            throw new RuntimeException(sprintf('The patches property in %s must be an object.', $patchesFile));
        }

        $patchMap = [];
        foreach ($patches as $packageName => $packagePatches) {
            if (!is_string($packageName) || !is_array($packagePatches)) {
                throw new RuntimeException(sprintf('The patches property in %s contains an invalid package entry.', $patchesFile));
            }

            foreach ($packagePatches as $description => $url) {
                if (!is_string($description) || !is_string($url)) {
                    throw new RuntimeException(sprintf('The patches property in %s contains an invalid patch.', $patchesFile));
                }

                $patchMap[$packageName][$description] = $url;
            }
        }

        return $patchMap;
    }

    /**
     * @param array<string, array<string, string>> $patchMap
     * @return array{patchMap: array<string, array<string, string>>, count: int}
     */
    private function removeGeneratedEntriesFromPatchMap(array $patchMap, bool $removeEmptyPackages = true): array
    {
        $count = 0;
        foreach ($patchMap as $packageName => $packagePatches) {
            foreach ($packagePatches as $description => $_url) {
                if (strpos($description, self::DESCRIPTION_PREFIX) === 0) {
                    unset($patchMap[$packageName][$description]);
                    ++$count;
                }
            }

            if ($removeEmptyPackages && $patchMap[$packageName] === []) {
                unset($patchMap[$packageName]);
            }
        }

        return [
            'patchMap' => $patchMap,
            'count' => $count,
        ];
    }

    /**
     * @param array<string, array<string, string>> $patchMap
     * @return array<string, array<string, string>>
     */
    private function removeEmptyPackages(array $patchMap): array
    {
        foreach ($patchMap as $packageName => $packagePatches) {
            if ($packagePatches === []) {
                unset($patchMap[$packageName]);
            }
        }

        return $patchMap;
    }

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, array<string, string>> $patchMap
     */
    private function writePatchMap(array &$configuration, string $patchesFile, bool $fromComposerExtra, array $patchMap): void
    {
        if ($fromComposerExtra) {
            $extra = $configuration['extra'] ?? [];
            if (!is_array($extra)) {
                throw new RuntimeException(sprintf('The extra property in %s must be an object.', $patchesFile));
            }

            $extra['patches'] = $patchMap;
            $configuration['extra'] = $extra;

            return;
        }

        $configuration['patches'] = $patchMap;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function writeConfiguration(string $patchesFile, array $configuration): void
    {
        $directory = dirname($patchesFile);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Cannot create Composer patches directory: %s', $directory));
        }

        $contents = json_encode($configuration, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Cannot encode Composer patches file %s: %s', $patchesFile, json_last_error_msg()));
        }
        $contents .= PHP_EOL;

        if (file_put_contents($patchesFile, $contents) === false) {
            throw new RuntimeException(sprintf('Cannot write Composer patches file: %s', $patchesFile));
        }
    }

    /**
     * @param array<mixed> $value
     */
    private function isList(array $value): bool
    {
        $expectedKey = 0;
        foreach ($value as $key => $_item) {
            if ($key !== $expectedKey) {
                return false;
            }

            ++$expectedKey;
        }

        return true;
    }
}
