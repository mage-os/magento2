<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Indexer\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

/**
 * Command for displaying information related to indexers.
 */
class IndexerInfoCommand extends AbstractIndexerCommand
{
    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('indexer:info')->setDescription('Shows allowed Indexers');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $indexers = $this->getAllIndexers();
        foreach ($indexers as $indexer) {
            $output->writeln(sprintf('%-40s %s', $indexer->getId(), $indexer->getTitle()));
        }
        return Command::SUCCESS; // Return success status code
    }
}
