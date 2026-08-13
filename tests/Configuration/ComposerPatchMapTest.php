<?php

declare(strict_types=1);

namespace Webidea24\MagentoComposerPatches\Tests\Configuration;

use PHPUnit\Framework\TestCase;
use Webidea24\MagentoComposerPatches\Configuration\ComposerPatchMap;

final class ComposerPatchMapTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'composer-magento-patches-' . uniqid('', true);
        mkdir($this->temporaryDirectory . '/patches', 0777, true);
        file_put_contents(
            $this->temporaryDirectory . '/patches/security.patch',
            <<<'PATCH'
diff --git a/vendor/magento/module-customer/Model/Customer.php b/vendor/magento/module-customer/Model/Customer.php
--- a/vendor/magento/module-customer/Model/Customer.php
+++ b/vendor/magento/module-customer/Model/Customer.php
@@ -1 +1 @@
-before
+after
diff --git a/nginx.conf.sample b/nginx.conf.sample
--- a/nginx.conf.sample
+++ b/nginx.conf.sample
@@ -1 +1 @@
-before
+after
PATCH
        );
        file_put_contents(
            $this->temporaryDirectory . '/composer.patches.json',
            <<<'JSON'
{
    "patches": {
        "magento/module-customer": {
            "Custom patch": "patches/custom.patch",
            "[webidea24/magento-composer-patches] Old patch": "https://old.example/patch.patch"
        }
    }
}
JSON
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryDirectory);
    }

    public function testItMergesAndRemovesOnlyItsOwnRemotePatches(): void
    {
        $patchesFile = $this->temporaryDirectory . '/composer.patches.json';
        $patchMap = new ComposerPatchMap();
        $mergedPatches = $patchMap->replaceGeneratedPatchUrls($patchesFile, 'https://patches.example/magento', [[
            'description' => 'Bundled Magento 2.4.9 security update 2026-07-001-CE',
            'package' => 'magento/module-customer',
            'path' => '2.4.9/security/magento-module-customer.patch',
        ]]);

        self::assertSame(1, $mergedPatches);
        $patches = $this->getCustomerPatches($patchesFile);
        self::assertSame('patches/custom.patch', $patches['Custom patch']);
        self::assertSame(
            'https://patches.example/magento/2.4.9/security/magento-module-customer.patch',
            $patches['[webidea24/magento-composer-patches] Bundled Magento 2.4.9 security update 2026-07-001-CE'],
        );

        self::assertSame(1, $patchMap->removeGeneratedPatchUrls($patchesFile));
        self::assertSame('patches/custom.patch', $this->getCustomerPatches($patchesFile)['Custom patch']);
        self::assertSame(0, $patchMap->removeGeneratedPatchUrls($patchesFile));
    }

    public function testItCanMergeDirectlyIntoComposerExtra(): void
    {
        $composerFile = $this->temporaryDirectory . '/composer.json';
        file_put_contents(
            $composerFile,
            <<<'JSON'
{
    "name": "acme/project",
    "extra": {
        "patches": {
            "magento/module-customer": {
                "Custom patch": "patches/custom.patch"
            }
        }
    }
}
JSON
        );

        $patchMap = new ComposerPatchMap();
        $patchMap->replaceGeneratedPatchUrls(
            $composerFile,
            'https://patches.example/magento',
            [[
                'description' => 'Bundled Magento 2.4.9 security update 2026-07-001-CE',
                'package' => 'magento/module-customer',
                'path' => '2.4.9/security/magento-module-customer.patch',
            ]],
            true,
        );

        $configuration = $this->readJson($composerFile);
        self::assertSame('acme/project', $configuration['name']);
        self::assertIsArray($configuration['extra']);
        self::assertIsArray($configuration['extra']['patches']);
        self::assertArrayHasKey('magento/module-customer', $configuration['extra']['patches']);
        self::assertIsArray($configuration['extra']['patches']['magento/module-customer']);
        self::assertSame(
            'https://patches.example/magento/2.4.9/security/magento-module-customer.patch',
            $configuration['extra']['patches']['magento/module-customer']['[webidea24/magento-composer-patches] Bundled Magento 2.4.9 security update 2026-07-001-CE'],
        );

        self::assertSame(1, $patchMap->removeGeneratedPatchUrls($composerFile, true));
        $configuration = $this->readJson($composerFile);
        self::assertIsArray($configuration['extra']);
        self::assertIsArray($configuration['extra']['patches']);
        self::assertSame(
            [
                'Custom patch' => 'patches/custom.patch',
            ],
            $configuration['extra']['patches']['magento/module-customer'],
        );
    }

    /**
     * @return array<string, string>
     */
    private function getCustomerPatches(string $path): array
    {
        $configuration = $this->readJson($path);
        self::assertArrayHasKey('patches', $configuration);
        self::assertIsArray($configuration['patches']);
        self::assertArrayHasKey('magento/module-customer', $configuration['patches']);
        self::assertIsArray($configuration['patches']['magento/module-customer']);

        $customerPatches = [];
        foreach ($configuration['patches']['magento/module-customer'] as $description => $url) {
            self::assertIsString($description);
            self::assertIsString($url);
            $customerPatches[$description] = $url;
        }

        return $customerPatches;
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $contents = file_get_contents($path);
        self::assertIsString($contents);
        $configuration = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($configuration);

        $result = [];
        foreach ($configuration as $key => $value) {
            self::assertIsString($key);
            $result[$key] = $value;
        }

        return $result;
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
