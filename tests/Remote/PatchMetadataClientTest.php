<?php

declare(strict_types=1);

namespace Webidea24\MagentoComposerPatches\Tests\Remote;

use PHPUnit\Framework\TestCase;
use Webidea24\MagentoComposerPatches\Remote\PatchMetadataClient;

final class PatchMetadataClientTest extends TestCase
{
    /**
     * @var string
     */
    private $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'composer-magento-patches-' . uniqid('', true);
        mkdir($this->temporaryDirectory, 0777, true);
        mkdir($this->temporaryDirectory . '/2.4.7-p10', 0777, true);
        file_put_contents(
            $this->temporaryDirectory . '/2.4.7-p10/meta.json',
            <<<'JSON'
{
    "patches": [
        {
            "name": "247p10-2026-07-001-CE",
            "files": [
                {
                    "package": "magento/module-quote",
                    "path": "2.4.7-p10/247p10-2026-07-001-CE/magento-module-quote.patch"
                }
            ]
        }
    ]
}
JSON
        );
    }

    protected function tearDown(): void
    {
        unlink($this->temporaryDirectory . '/2.4.7-p10/meta.json');
        rmdir($this->temporaryDirectory . '/2.4.7-p10');
        rmdir($this->temporaryDirectory);
    }

    public function testItReturnsOnlyPatchesForTheRequestedMagentoVersion()
    {
        self::assertSame([
            [
                'description' => '247p10-2026-07-001-CE',
                'package' => 'magento/module-quote',
                'path' => '2.4.7-p10/247p10-2026-07-001-CE/magento-module-quote.patch',
            ],
        ], (new PatchMetadataClient())->getPatchesForMagentoVersion(
            'file://' . $this->temporaryDirectory,
            '2.4.7-p10'
        ));
    }
}
