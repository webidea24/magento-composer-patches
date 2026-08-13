<?php

declare(strict_types=1);

namespace Webidea24\MagentoComposerPatches\Tests\Build;

use PHPUnit\Framework\TestCase;
use Webidea24\MagentoComposerPatches\Build\PatchFileBuilder;

final class PatchFileBuilderTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'composer-magento-patches-' . uniqid('', true);
        mkdir($this->temporaryDirectory . '/package/magento-patches/2.4.9/security-patches', 0777, true);
        file_put_contents(
            $this->temporaryDirectory . '/package/magento-patches/2.4.9/security-patches/249-2026-07-001-CE.patch',
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
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryDirectory);
    }

    public function testItWritesOnePatchPerPackageAndResolvesMappedRootFragments(): void
    {
        $outputDirectory = $this->temporaryDirectory . '/build/patches';
        $messages = [];
        $writtenFiles = (new PatchFileBuilder(
            logger: static function (string $message) use (&$messages): void {
                $messages[] = $message;
            },
        ))->build($this->temporaryDirectory . '/package', $outputDirectory, [
            '2.4.9' => [
                'nginx.conf.sample' => [
                    'package' => 'magento/magento2-base',
                    'source' => 'nginx.conf.sample',
                ],
            ],
        ]);

        self::assertSame(2, $writtenFiles);
        self::assertContains('[2.4.9] Mapping nginx.conf.sample to magento/magento2-base (nginx.conf.sample).', $messages);
        self::assertContains('[2.4.9] Wrote metadata.', $messages);
        $customerPatch = glob($outputDirectory . '/2.4.9/249-2026-07-001-CE/magento-module-customer-*.patch');
        $basePatch = glob($outputDirectory . '/2.4.9/249-2026-07-001-CE/magento-magento2-base-*.patch');
        self::assertIsArray($customerPatch);
        self::assertIsArray($basePatch);
        self::assertCount(1, $customerPatch);
        self::assertCount(1, $basePatch);
        self::assertMatchesRegularExpression('/magento-module-customer-[a-f0-9]{8}\\.patch$/', $customerPatch[0]);
        $customerContents = file_get_contents($customerPatch[0]);
        self::assertIsString($customerContents);
        self::assertStringEndsWith('-' . substr(hash('sha256', $customerContents), 0, 8) . '.patch', $customerPatch[0]);

        $metadata = file_get_contents($outputDirectory . '/2.4.9/meta.json');
        self::assertIsString($metadata);
        self::assertSame([
            'patches' => [[
                'name' => '249-2026-07-001-CE',
                'files' => [[
                    'package' => 'magento/magento2-base',
                    'path' => '2.4.9/249-2026-07-001-CE/' . basename($basePatch[0]),
                ], [
                    'package' => 'magento/module-customer',
                    'path' => '2.4.9/249-2026-07-001-CE/' . basename($customerPatch[0]),
                ]],
            ]],
        ], json_decode($metadata, true, 512, JSON_THROW_ON_ERROR));
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
