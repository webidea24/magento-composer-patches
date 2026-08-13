<?php

declare(strict_types=1);

namespace Webidea24\MagentoComposerPatches\Configuration;

use Composer\Composer;
use InvalidArgumentException;
use RuntimeException;
use Webidea24\MagentoComposerPatches\Remote\PatchMetadataClient;

/**
 * Selects the installed Magento version and updates the Composer patch map.
 */
final class PatchUrlSynchronizer
{
    const DEFAULT_PATCH_BASE_URL = 'https://patches.webidea.dev/security/magento/';

    /**
     * @var Composer
     */
    private $composer;

    public function __construct(Composer $composer)
    {
        $this->composer = $composer;
    }

    public function synchronize()
    {
        $version = $this->findInstalledMagentoVersion();
        if ($version === null) {
            throw new InvalidArgumentException('Cannot determine an exact installed Magento Open Source version.');
        }

        $composerFile = $this->getRootComposerFile();
        $composerConfiguration = $this->readComposerConfiguration($composerFile);
        $patchesFile = $this->resolvePatchConfigurationFile($composerFile, $composerConfiguration);
        $patchBaseUrl = $this->readPatchBaseUrl($composerConfiguration);
        $patches = (new PatchMetadataClient())->getPatchesForMagentoVersion($patchBaseUrl, $version);

        return (new ComposerPatchMap())->replaceGeneratedPatchUrls(
            $patchesFile['path'],
            $patchBaseUrl,
            $patches,
            $patchesFile['isComposerFile']
        );
    }

    private function findInstalledMagentoVersion()
    {
        foreach ($this->composer->getRepositoryManager()->getLocalRepository()->getPackages() as $package) {
            if ($package->getName() === 'magento/product-community-edition') {
                return $this->toExactMagentoVersion($package->getPrettyVersion());
            }
        }

        $requires = $this->composer->getPackage()->getRequires();
        $require = isset($requires['magento/product-community-edition'])
            ? $requires['magento/product-community-edition']
            : null;

        return $require === null ? null : $this->toExactMagentoVersion($require->getPrettyConstraint());
    }

    /**
     * @param array $composerConfiguration
     */
    private function readPatchBaseUrl(array $composerConfiguration)
    {
        $extra = isset($composerConfiguration['extra']) ? $composerConfiguration['extra'] : array();
        if (!is_array($extra)) {
            throw new InvalidArgumentException('extra must be an object.');
        }

        $configuration = isset($extra['composer-magento-patches'])
            ? $extra['composer-magento-patches']
            : array();
        if (!is_array($configuration)) {
            throw new InvalidArgumentException('extra.composer-magento-patches must be an object.');
        }

        $baseUrl = isset($configuration['patch-base-url'])
            ? $configuration['patch-base-url']
            : self::DEFAULT_PATCH_BASE_URL;
        if (!is_string($baseUrl) || trim($baseUrl) === '') {
            throw new InvalidArgumentException('extra.composer-magento-patches.patch-base-url must be a non-empty URL.');
        }

        return $baseUrl;
    }

    private function getRootComposerFile()
    {
        $workingDirectory = getcwd();
        if ($workingDirectory === false) {
            throw new RuntimeException('Cannot determine the Composer project directory.');
        }

        $composerFile = getenv('COMPOSER');
        $composerFile = is_string($composerFile) && trim($composerFile) !== '' ? trim($composerFile) : 'composer.json';
        $composerFile = $this->isAbsolutePath($composerFile)
            ? $composerFile
            : $workingDirectory . DIRECTORY_SEPARATOR . $composerFile;
        if (!is_file($composerFile)) {
            throw new RuntimeException(sprintf('Cannot read Composer configuration: %s', $composerFile));
        }

        return $composerFile;
    }

    /**
     * @return array
     */
    private function readComposerConfiguration($composerFile)
    {
        $contents = file_get_contents($composerFile);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Cannot read Composer configuration: %s', $composerFile));
        }

        $configuration = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($configuration)) {
            throw new RuntimeException(sprintf('Cannot parse Composer configuration: %s', $composerFile));
        }

        foreach ($configuration as $key => $value) {
            if (!is_string($key)) {
                throw new RuntimeException(sprintf('Composer configuration must contain a JSON object: %s', $composerFile));
            }
        }

        return $configuration;
    }

    /**
     * @param array $composerConfiguration
     * @return array
     */
    private function resolvePatchConfigurationFile($composerFile, array $composerConfiguration)
    {
        $extra = isset($composerConfiguration['extra']) ? $composerConfiguration['extra'] : array();
        if (!is_array($extra)) {
            throw new InvalidArgumentException('extra must be an object.');
        }

        if (!array_key_exists('patches-file', $extra)) {
            return array(
                'path' => $composerFile,
                'isComposerFile' => true,
            );
        }

        $patchesFile = $extra['patches-file'];
        if (!is_string($patchesFile) || trim($patchesFile) === '') {
            throw new InvalidArgumentException('extra.patches-file must be a non-empty file path.');
        }

        return array(
            'path' => $this->isAbsolutePath($patchesFile)
                ? $patchesFile
                : dirname($composerFile) . DIRECTORY_SEPARATOR . $patchesFile,
            'isComposerFile' => false,
        );
    }

    private function isAbsolutePath($path)
    {
        return strpos($path, DIRECTORY_SEPARATOR) === 0
            || preg_match('{^[A-Za-z]:[\\/]}', $path) === 1;
    }

    private function toExactMagentoVersion($version)
    {
        return preg_match('/^\d+\.\d+\.\d+(?:-p\d+)?$/', $version) === 1 ? $version : null;
    }
}
