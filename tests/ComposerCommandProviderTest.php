<?php

declare(strict_types=1);

namespace Webidea24\MagentoComposerPatches\Tests;

use Composer\Composer;
use Composer\IO\IOInterface;
use PHPUnit\Framework\TestCase;
use Webidea24\MagentoComposerPatches\Command\SynchronizePatchesCommand;
use Webidea24\MagentoComposerPatches\ComposerCommandProvider;

final class ComposerCommandProviderTest extends TestCase
{
    public function testItRegistersOnlyTheSynchronizeCommand(): void
    {
        $provider = new ComposerCommandProvider([
            'composer' => $this->createMock(Composer::class),
            'io' => $this->createMock(IOInterface::class),
        ]);

        $commands = $provider->getCommands();

        self::assertCount(1, $commands);
        self::assertInstanceOf(SynchronizePatchesCommand::class, $commands[0]);
        self::assertSame('magento-patches:sync', $commands[0]->getName());
    }
}
