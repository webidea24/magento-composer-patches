<?php

declare(strict_types=1);

namespace Webidea24\MagentoComposerPatches\Build;

use RuntimeException;

/**
 * Resolves Magento extra.map entries through Composer without installing vendor files.
 */
final class MagentoPackageMapResolver
{
    /**
     * @return array<string, array{package: string, source: string}>
     */
    public function resolve(string $magentoVersion): array
    {
        $workingDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'magento-composer-patches-' . uniqid('', true);
        if (!mkdir($workingDirectory, 0700, true) && !is_dir($workingDirectory)) {
            throw new RuntimeException(sprintf('Cannot create temporary Composer directory: %s', $workingDirectory));
        }

        try {
            $composerFile = $workingDirectory . DIRECTORY_SEPARATOR . 'composer.json';
            $this->writeComposerConfiguration($composerFile, $magentoVersion);
            $this->runComposer($composerFile, $workingDirectory, $magentoVersion);

            return (new ComposerPackageMapReader())->read($workingDirectory . DIRECTORY_SEPARATOR . 'composer.lock');
        } finally {
            $this->removeDirectory($workingDirectory);
        }
    }

    private function writeComposerConfiguration(string $composerFile, string $magentoVersion): void
    {
        $configuration = json_encode([
            'repositories' => [[
                'type' => 'composer',
                'url' => 'https://mirror.mage-os.org/',
            ]],
            'require' => [
                'magento/product-community-edition' => $magentoVersion,
            ],
            'config' => [
                'audit' => [
                    'block-insecure' => false,
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;

        if (file_put_contents($composerFile, $configuration) === false) {
            throw new RuntimeException(sprintf('Cannot write temporary Composer configuration: %s', $composerFile));
        }
    }

    private function runComposer(string $composerFile, string $workingDirectory, string $magentoVersion): void
    {
        $command = sprintf(
            'COMPOSER=%s composer update --no-install --no-plugins --no-interaction --no-progress --ignore-platform-reqs --no-audit',
            escapeshellarg($composerFile),
        );
        $process = proc_open($command, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $workingDirectory);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start Composer to resolve Magento package mappings.');
        }

        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException(sprintf('Cannot resolve Magento %s package mappings:%s%s', $magentoVersion, PHP_EOL, trim($output)));
        }
    }

    private function removeDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
