<?php

declare(strict_types=1);

namespace Webidea24\MagentoComposerPatches\Build;

use JsonException;
use RuntimeException;

/**
 * Reads Magento extra.map entries from Composer package metadata.
 */
final class ComposerPackageMapReader
{
    /**
     * @return array<string, array{package: string, source: string}>
     */
    public function read(string $composerLockFile): array
    {
        $contents = file_get_contents($composerLockFile);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Cannot read Composer lock file: %s', $composerLockFile));
        }

        try {
            $lock = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new RuntimeException(sprintf('Cannot parse Composer lock file %s: %s', $composerLockFile, $jsonException->getMessage()), 0, $jsonException);
        }

        if (!is_array($lock) || !isset($lock['packages']) || !is_array($lock['packages'])) {
            throw new RuntimeException(sprintf('Composer lock file does not contain packages: %s', $composerLockFile));
        }

        $mappings = [];
        foreach ($lock['packages'] as $package) {
            if (!is_array($package) || !is_string($package['name'] ?? null)) {
                continue;
            }

            $extra = $package['extra'] ?? [];
            if (!is_array($extra) || !isset($extra['map']) || !is_array($extra['map'])) {
                continue;
            }

            foreach ($extra['map'] as $map) {
                if (!is_array($map) || count($map) !== 2 || !is_string($map[0]) || !is_string($map[1])) {
                    throw new RuntimeException(sprintf('Package %s contains an invalid extra.map entry.', $package['name']));
                }

                $source = $this->normalizePath($map[0]);
                $target = $this->normalizePath($map[1]);
                if ($source === '' || $target === '') {
                    throw new RuntimeException(sprintf('Package %s contains an invalid extra.map path.', $package['name']));
                }

                if (isset($mappings[$target])) {
                    throw new RuntimeException(sprintf('Multiple Composer packages map the Magento path %s.', $target));
                }

                $mappings[$target] = [
                    'package' => $package['name'],
                    'source' => $source,
                ];
            }
        }

        return $mappings;
    }

    private function normalizePath(string $path): string
    {
        return trim(preg_replace('#^(?:\./)+#', '', str_replace('\\', '/', $path)) ?? $path, '/');
    }
}
