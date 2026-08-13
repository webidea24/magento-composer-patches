<?php

declare(strict_types=1);

namespace Webidea24\MagentoComposerPatches\Command;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webidea24\MagentoComposerPatches\Configuration\PatchUrlSynchronizer;

final class SynchronizePatchesCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('magento-patches:sync')
            ->setDescription('Merge matching Magento patch URLs.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $synchronizedPatches = (new PatchUrlSynchronizer($this->requireComposer()))->synchronize();
        $this->getIO()
            ->write(sprintf(
                '<info>Merged %d remote package patch%s.</info>',
                $synchronizedPatches,
                $synchronizedPatches === 1 ? '' : 'es'
            ));

        return 0;
    }
}
