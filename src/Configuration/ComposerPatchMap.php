<?php

declare(strict_types=1);

namespace Webidea24\MagentoComposerPatches\Configuration;

use RuntimeException;

/**
 * Merges remote vendor patches into a Composer patches file.
 */
final class ComposerPatchMap
{
    const DESCRIPTION_PREFIX = '[webidea24/magento-composer-patches] ';

    /**
     * @param array $patches
     */
    public function replaceGeneratedPatchUrls($patchesFile, $baseUrl, array $patches, $mergeIntoComposerExtra = false)
    {
        $configuration = $this->readConfiguration($patchesFile);
        $patchMap = $this->readPatchMap($configuration, $patchesFile, $mergeIntoComposerExtra);
        $patchMap = $this->removeGeneratedEntries($patchMap);

        foreach ($patches as $patch) {
            $packageName = $patch['package'];
            if (!isset($patchMap[$packageName])) {
                $patchMap[$packageName] = array();
            }

            $patchMap[$packageName][self::DESCRIPTION_PREFIX . $patch['description']] = rtrim($baseUrl, '/')
                . '/' . ltrim($patch['path'], '/');
        }

        $this->writePatchMap($configuration, $mergeIntoComposerExtra, $patchMap);
        $this->writeConfiguration($patchesFile, $configuration);

        return count($patches);
    }

    /**
     * @return array
     */
    private function readConfiguration($patchesFile)
    {
        if (!is_file($patchesFile)) {
            return array();
        }

        $contents = file_get_contents($patchesFile);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Cannot read Composer patches file: %s', $patchesFile));
        }

        $configuration = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($configuration)) {
            throw new RuntimeException(sprintf('Cannot parse Composer patches file: %s', $patchesFile));
        }

        foreach ($configuration as $key => $value) {
            if (!is_string($key)) {
                throw new RuntimeException(sprintf('Composer patches file must contain a JSON object: %s', $patchesFile));
            }
        }

        return $configuration;
    }

    /**
     * @param array $configuration
     * @return array
     */
    private function readPatchMap(array $configuration, $patchesFile, $fromComposerExtra)
    {
        if ($fromComposerExtra) {
            $extra = isset($configuration['extra']) ? $configuration['extra'] : array();
            if (!is_array($extra)) {
                throw new RuntimeException(sprintf('The extra property in %s must be an object.', $patchesFile));
            }
            $patches = isset($extra['patches']) ? $extra['patches'] : array();
        } else {
            $patches = isset($configuration['patches']) ? $configuration['patches'] : array();
        }

        if (!is_array($patches)) {
            throw new RuntimeException(sprintf('The patches property in %s must be an object.', $patchesFile));
        }

        foreach ($patches as $packageName => $packagePatches) {
            if (!is_string($packageName) || !is_array($packagePatches)) {
                throw new RuntimeException(sprintf('The patches property in %s contains an invalid package entry.', $patchesFile));
            }

            foreach ($packagePatches as $description => $url) {
                if (!is_string($description) || !is_string($url)) {
                    throw new RuntimeException(sprintf('The patches property in %s contains an invalid patch.', $patchesFile));
                }
            }
        }

        return $patches;
    }

    /**
     * @param array $patchMap
     * @return array
     */
    private function removeGeneratedEntries(array $patchMap)
    {
        foreach ($patchMap as $packageName => $packagePatches) {
            foreach ($packagePatches as $description => $url) {
                if (strpos($description, self::DESCRIPTION_PREFIX) === 0) {
                    unset($patchMap[$packageName][$description]);
                }
            }

            if ($patchMap[$packageName] === array()) {
                unset($patchMap[$packageName]);
            }
        }

        return $patchMap;
    }

    /**
     * @param array $configuration
     * @param array $patchMap
     */
    private function writePatchMap(array &$configuration, $fromComposerExtra, array $patchMap)
    {
        if ($fromComposerExtra) {
            $extra = isset($configuration['extra']) ? $configuration['extra'] : array();
            if (!is_array($extra)) {
                throw new RuntimeException('The extra property must be an object.');
            }
            $extra['patches'] = $patchMap;
            $configuration['extra'] = $extra;

            return;
        }

        $configuration['patches'] = $patchMap;
    }

    /**
     * @param array $configuration
     */
    private function writeConfiguration($patchesFile, array $configuration)
    {
        $directory = dirname($patchesFile);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Cannot create Composer patches directory: %s', $directory));
        }

        $contents = json_encode($configuration, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Cannot encode Composer patches file: %s', $patchesFile));
        }

        if (file_put_contents($patchesFile, $contents . PHP_EOL) === false) {
            throw new RuntimeException(sprintf('Cannot write Composer patches file: %s', $patchesFile));
        }
    }
}
