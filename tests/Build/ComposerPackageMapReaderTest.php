<?php

declare(strict_types=1);

namespace Webidea24\MagentoComposerPatches\Tests\Build;

use PHPUnit\Framework\TestCase;
use Webidea24\MagentoComposerPatches\Build\ComposerPackageMapReader;

final class ComposerPackageMapReaderTest extends TestCase
{
    private string $composerLockFile;

    protected function setUp(): void
    {
        $this->composerLockFile = tempnam(sys_get_temp_dir(), 'composer-magento-patches-');
        self::assertNotFalse($this->composerLockFile);
        file_put_contents(
            $this->composerLockFile,
            <<<'JSON'
{
    "packages": [
        {
            "name": "magento/magento2-base",
            "extra": {
                "map": [
                    [
                        "nginx.conf.sample",
                        "nginx.conf.sample"
                    ],
                    [
                        "pub/errors",
                        "pub/errors"
                    ]
                ]
            }
        }
    ]
}
JSON
        );
    }

    protected function tearDown(): void
    {
        unlink($this->composerLockFile);
    }

    public function testItReadsPackageMappingsFromTheComposerLockFile(): void
    {
        self::assertSame([
            'nginx.conf.sample' => [
                'package' => 'magento/magento2-base',
                'source' => 'nginx.conf.sample',
            ],
            'pub/errors' => [
                'package' => 'magento/magento2-base',
                'source' => 'pub/errors',
            ],
        ], (new ComposerPackageMapReader())->read($this->composerLockFile));
    }
}
