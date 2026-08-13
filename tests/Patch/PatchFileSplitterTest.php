<?php

declare(strict_types=1);

namespace Webidea24\MagentoComposerPatches\Tests\Patch;

use PHPUnit\Framework\TestCase;
use Webidea24\MagentoComposerPatches\Patch\PatchFileSplitter;

final class PatchFileSplitterTest extends TestCase
{
    private string $patchPath;

    protected function setUp(): void
    {
        $this->patchPath = tempnam(sys_get_temp_dir(), 'composer-magento-patches-');
        self::assertNotFalse($this->patchPath);
    }

    protected function tearDown(): void
    {
        @unlink($this->patchPath);
    }

    public function testItSplitsAUnifiedDiffThatAddsARootFile(): void
    {
        file_put_contents(
            $this->patchPath,
            <<<'PATCH'
--- a/dev/null
+++ b/nginx.conf.sample
@@ -0,0 +1 @@
+new line
PATCH
        );

        $contents = file_get_contents($this->patchPath);
        self::assertIsString($contents);

        self::assertSame([[
            'contents' => str_replace('--- a/dev/null', '--- /dev/null', $contents),
            'target' => 'nginx.conf.sample',
            'vendorPackage' => null,
        ]], PatchFileSplitter::splitByFile($this->patchPath));
    }

    public function testItClassifiesAUnifiedDiffThatDeletesAVendorFile(): void
    {
        file_put_contents(
            $this->patchPath,
            <<<'PATCH'
--- a/vendor/magento/module-customer/Model/Customer.php
+++ /dev/null
@@ -1 +0,0 @@
-old line
PATCH
        );

        $fragment = PatchFileSplitter::splitByFile($this->patchPath)[0];

        self::assertSame('magento/module-customer', $fragment['vendorPackage']);
    }

    public function testItRewritesAFragmentForItsMappedPackageSource(): void
    {
        self::assertSame(
            <<<'PATCH'
diff --git a/etc/nginx.conf.sample b/etc/nginx.conf.sample
--- a/etc/nginx.conf.sample
+++ b/etc/nginx.conf.sample
@@ -1 +1 @@
-before
+after
PATCH
            ,
            PatchFileSplitter::replaceTargetPath(
                <<<'PATCH'
diff --git a/nginx.conf.sample b/nginx.conf.sample
--- a/nginx.conf.sample
+++ b/nginx.conf.sample
@@ -1 +1 @@
-before
+after
PATCH
                ,
                'nginx.conf.sample',
                'etc/nginx.conf.sample',
            ),
        );
    }
}
