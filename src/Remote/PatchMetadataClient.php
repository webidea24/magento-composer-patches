<?php

declare(strict_types=1);

namespace Webidea24\MagentoComposerPatches\Remote;

use RuntimeException;

/**
 * Retrieves package patch paths from a published patch metadata file.
 */
final class PatchMetadataClient
{
    /**
     * @return array
     */
    public function getPatchesForMagentoVersion($patchBaseUrl, $magentoVersion)
    {
        $metadataUrl = rtrim($patchBaseUrl, '/') . '/' . rawurlencode($magentoVersion) . '/meta.json';
        $contents = file_get_contents($metadataUrl);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Cannot download patch metadata: %s', $metadataUrl));
        }

        $metadata = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($metadata) || !isset($metadata['patches']) || !is_array($metadata['patches'])) {
            throw new RuntimeException(sprintf('Patch metadata must contain a patches array: %s', $metadataUrl));
        }

        $patches = array();
        foreach ($metadata['patches'] as $patch) {
            if (!is_array($patch) || !isset($patch['name']) || !is_string($patch['name'])
                || !isset($patch['files']) || !is_array($patch['files'])) {
                throw new RuntimeException(sprintf('Patch metadata contains an invalid patch entry: %s', $metadataUrl));
            }

            foreach ($patch['files'] as $file) {
                if (!is_array($file) || !isset($file['package']) || !is_string($file['package'])
                    || !isset($file['path']) || !is_string($file['path'])) {
                    throw new RuntimeException(sprintf('Patch metadata contains an invalid patch file: %s', $metadataUrl));
                }

                $patches[] = array(
                    'description' => $patch['name'],
                    'package' => $file['package'],
                    'path' => $file['path'],
                );
            }
        }

        return $patches;
    }
}
