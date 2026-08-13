<?php

declare(strict_types=1);

namespace Webidea24\MagentoComposerPatches;

use Composer\Command\BaseCommand;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider;
use Webidea24\MagentoComposerPatches\Command\SynchronizePatchesCommand;

final class ComposerCommandProvider implements CommandProvider
{
    /**
     * @var array{composer: Composer, io: IOInterface}
     */
    private $arguments;

    /**
     * @param array{composer: Composer, io: IOInterface} $arguments
     */
    public function __construct(array $arguments)
    {
        $this->arguments = $arguments;
    }

    /**
     * @return list<BaseCommand>
     */
    public function getCommands()
    {
        $commands = [new SynchronizePatchesCommand()];
        foreach ($commands as $command) {
            $command->setComposer($this->arguments['composer']);
            $command->setIO($this->arguments['io']);
        }

        return $commands;
    }
}
