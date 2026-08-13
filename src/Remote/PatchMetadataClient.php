<?php

declare(strict_types=1);

namespace Webidea24\MagentoComposerPatches\Remote;

use JsonException;
use RuntimeException;

/**
 * Retrieves package patch paths from a published patch metadata file.
 */
final class PatchMetadataClient
{
    /**
     * @return list<array{description: string, package: string, path: string}>
     */
    public function getPatchesForMagentoVersion(string $patchBaseUrl, string $magentoVersion): array
    {
        $metadataUrl = rtrim($patchBaseUrl, '/') . '/' . rawurlencode($magentoVersion) . '/meta.json';
        $contents = file_get_contents($metadataUrl);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Cannot download patch metadata: %s', $metadataUrl));
        }

        try {
            $metadata = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new RuntimeException(sprintf('Cannot parse patch metadata %s: %s', $metadataUrl, $jsonException->getMessage()), 0, $jsonException);
        }

        if (!is_array($metadata) || array_is_list($metadata) || !isset($metadata['patches']) || !is_array($metadata['patches'])) {
            throw new RuntimeException(sprintf('Patch metadata must contain a patches array: %s', $metadataUrl));
        }

        $patches = [];
        foreach ($metadata['patches'] as $patch) {
            if (!is_array($patch)) {
                throw new RuntimeException(sprintf('Patch metadata contains an invalid patch entry: %s', $metadataUrl));
            }

            $name = $patch['name'] ?? null;
            $files = $patch['files'] ?? null;
            if (!is_string($name) || !is_array($files)) {
                throw new RuntimeException(sprintf('Patch metadata contains an invalid patch entry: %s', $metadataUrl));
            }

            foreach ($files as $file) {
                if (!is_array($file)) {
                    throw new RuntimeException(sprintf('Patch metadata contains an invalid patch file: %s', $metadataUrl));
                }

                $package = $file['package'] ?? null;
                $path = $file['path'] ?? null;
                if (!is_string($package) || !is_string($path)) {
                    throw new RuntimeException(sprintf('Patch metadata contains an invalid patch file: %s', $metadataUrl));
                }

                $patches[] = [
                    'description' => $name,
                    'package' => $package,
                    'path' => $path,
                ];
            }
        }

        return $patches;
    }
}
