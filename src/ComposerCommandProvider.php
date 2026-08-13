<?php

declare(strict_types=1);

namespace Webidea24\MagentoComposerPatches;

use Composer\Command\BaseCommand;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider;
use Webidea24\MagentoComposerPatches\Command\RemovePatchesCommand;
use Webidea24\MagentoComposerPatches\Command\SynchronizePatchesCommand;

final class ComposerCommandProvider implements CommandProvider
{
    /**
     * @param array{composer: Composer, io: IOInterface} $arguments
     */
    public function __construct(
        private readonly array $arguments,
    ) {
    }

    /**
     * @return list<BaseCommand>
     */
    public function getCommands(): array
    {
        $commands = [
            new SynchronizePatchesCommand(),
            new RemovePatchesCommand(),
        ];
        foreach ($commands as $command) {
            $command->setComposer($this->arguments['composer']);
            $command->setIO($this->arguments['io']);
        }

        return $commands;
    }
}
