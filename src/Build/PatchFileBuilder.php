<?php

declare(strict_types=1);

namespace Webidea24\MagentoComposerPatches\Build;

use Closure;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Webidea24\MagentoComposerPatches\Patch\PatchFileSplitter;

/**
 * Builds package-scoped patch files for publication.
 */
final class PatchFileBuilder
{
    public function __construct(
        private readonly MagentoPackageMapResolver $packageMapResolver = new MagentoPackageMapResolver(),
        private readonly ?Closure $logger = null,
    ) {
    }

    /**
     * @param array<string, array<string, array{package: string, source: string}>> $packageMapsByMagentoVersion
     */
    public function build(string $packageRoot, string $outputDirectory, array $packageMapsByMagentoVersion = []): int
    {
        $sourceDirectory = $packageRoot . DIRECTORY_SEPARATOR . 'magento-patches';
        if (!is_dir($sourceDirectory)) {
            throw new RuntimeException(sprintf('Magento patch directory does not exist: %s', $sourceDirectory));
        }

        $this->log(sprintf('Preparing build directory: %s', $outputDirectory));
        $this->clearDirectory($outputDirectory);
        $writtenFiles = 0;
        $patchesByMagentoVersion = [];
        $patchFiles = $this->findPatchFiles($sourceDirectory);
        $this->log(sprintf('Found %d source patch file(s).', count($patchFiles)));

        foreach ($patchFiles as $patchFile) {
            $relativePath = substr($patchFile, strlen(rtrim($sourceDirectory, DIRECTORY_SEPARATOR)) + 1);
            $pathParts = explode(DIRECTORY_SEPARATOR, $relativePath);
            if (count($pathParts) < 2) {
                throw new RuntimeException(sprintf('Patch file must be inside a Magento-version directory: %s', $patchFile));
            }

            $magentoVersion = array_shift($pathParts);
            $patchName = pathinfo($patchFile, PATHINFO_FILENAME);
            $fragmentsByPackage = [];
            $fragments = PatchFileSplitter::splitByFile($patchFile);
            $this->log(sprintf('[%s] Processing %s (%d fragment(s)).', $magentoVersion, $patchName, count($fragments)));
            foreach ($fragments as $fragment) {
                $packageName = $fragment['vendorPackage'];
                $contents = $fragment['contents'];
                if ($packageName === null) {
                    if (!isset($packageMapsByMagentoVersion[$magentoVersion])) {
                        $this->log(sprintf('[%s] Resolving Composer package maps.', $magentoVersion));
                        $packageMapsByMagentoVersion[$magentoVersion] = $this->packageMapResolver->resolve($magentoVersion);
                        $this->log(sprintf(
                            '[%s] Resolved %d Composer package map(s).',
                            $magentoVersion,
                            count($packageMapsByMagentoVersion[$magentoVersion]),
                        ));
                    }

                    $mappedPackage = $this->findMappedPackage(
                        $fragment['target'],
                        $packageMapsByMagentoVersion[$magentoVersion],
                    );
                    if ($mappedPackage === null) {
                        $this->log(sprintf(
                            '[%s] Skipping %s: no Composer package map found.',
                            $magentoVersion,
                            $fragment['target'],
                        ));
                        continue;
                    }

                    $packageName = $mappedPackage['package'];
                    $this->log(sprintf(
                        '[%s] Mapping %s to %s (%s).',
                        $magentoVersion,
                        $fragment['target'],
                        $packageName,
                        $mappedPackage['source'],
                    ));
                    $contents = PatchFileSplitter::replaceTargetPath(
                        $contents,
                        $fragment['target'],
                        $mappedPackage['source'],
                    );
                }

                $fragmentsByPackage[$packageName][] = $contents;
            }

            foreach ($fragmentsByPackage as $packageName => $fragments) {
                $contents = implode('', $fragments);
                $fingerprint = substr(hash('sha256', $contents), 0, 8);
                $relativeDestination = implode('/', [
                    $magentoVersion,
                    $patchName,
                    str_replace('/', '-', $packageName) . '-' . $fingerprint . '.patch',
                ]);
                $destination = $outputDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDestination);
                $this->write($destination, $contents);
                $this->log(sprintf('[%s] Wrote %s.', $magentoVersion, $relativeDestination));
                $patchesByMagentoVersion[$magentoVersion][$patchName][] = [
                    'package' => $packageName,
                    'path' => $relativeDestination,
                ];
                ++$writtenFiles;
            }
        }

        foreach ($patchesByMagentoVersion as $magentoVersion => $patchesByName) {
            ksort($patchesByName, SORT_STRING);
            $patches = [];
            foreach ($patchesByName as $patchName => $files) {
                usort($files, static fn (array $first, array $second): int => $first['path'] <=> $second['path']);
                $patches[] = [
                    'name' => $patchName,
                    'files' => $files,
                ];
            }

            $this->writeMetadata($outputDirectory . DIRECTORY_SEPARATOR . $magentoVersion, $patches);
            $this->log(sprintf('[%s] Wrote metadata.', $magentoVersion));
        }

        return $writtenFiles;
    }

    private function log(string $message): void
    {
        if ($this->logger instanceof Closure) {
            ($this->logger)($message);
        }
    }

    /**
     * @param array<string, array{package: string, source: string}> $packageMappings
     * @return array{package: string, source: string}|null
     */
    private function findMappedPackage(string $targetPath, array $packageMappings): ?array
    {
        $targetPath = trim($targetPath, '/');
        uksort($packageMappings, static fn (string $first, string $second): int => strlen($second) <=> strlen($first));
        foreach ($packageMappings as $mappedTarget => $mapping) {
            if ($targetPath !== $mappedTarget && !str_starts_with($targetPath, $mappedTarget . '/')) {
                continue;
            }

            $suffix = substr($targetPath, strlen($mappedTarget));

            return [
                'package' => $mapping['package'],
                'source' => $mapping['source'] . $suffix,
            ];
        }

        return null;
    }

    /**
     * @param list<array{name: string, files: list<array{package: string, path: string}>}> $patches
     */
    private function writeMetadata(string $outputDirectory, array $patches): void
    {
        try {
            $contents = json_encode([
                'patches' => $patches,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        } catch (JsonException $jsonException) {
            throw new RuntimeException(sprintf('Cannot encode patch metadata: %s', $jsonException->getMessage()), 0, $jsonException);
        }

        if (file_put_contents($outputDirectory . DIRECTORY_SEPARATOR . 'meta.json', $contents) === false) {
            throw new RuntimeException(sprintf('Cannot write patch metadata in: %s', $outputDirectory));
        }
    }

    /**
     * @return list<string>
     */
    private function findPatchFiles(string $sourceDirectory): array
    {
        $patchFiles = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $sourceDirectory,
            RecursiveDirectoryIterator::SKIP_DOTS,
        ));
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'patch') {
                $patchFiles[] = $file->getPathname();
            }
        }

        sort($patchFiles, SORT_STRING);

        return $patchFiles;
    }

    private function clearDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );
            /** @var SplFileInfo $entry */
            foreach ($iterator as $entry) {
                if ($entry->isDir()) {
                    rmdir($entry->getPathname());
                    continue;
                }

                unlink($entry->getPathname());
            }
        }

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Cannot create build directory: %s', $directory));
        }
    }

    private function write(string $path, string $contents): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Cannot create patch build directory: %s', $directory));
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException(sprintf('Cannot write built patch: %s', $path));
        }
    }
}
