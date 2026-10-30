<?php

namespace YesWiki\Search\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\SearchIndexSchema;

/**
 * `./yeswicli search:reindex` -- the one way the search index is (re)built (ticket 18).
 *
 * It only queues: the reindexing is the background queue's to run (ADR-0030), in whatever mode
 * the wiki consumes it, or at once with `./yeswicli messenger:drain`.
 */
class ReindexCommand extends Command
{
    private ContainerInterface $services;

    public function __construct(ContainerInterface $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    protected function configure(): void
    {
        $this
            ->setName('search:reindex')
            ->setDescription('Build or repair the search index (ticket 18)')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Queue every Content')
            ->addOption('rebuild', null, InputOption::VALUE_NONE, 'Drop and recreate the index tables, then queue everything')
            ->addOption('form', null, InputOption::VALUE_REQUIRED, 'Queue one form\'s entries')
            ->addOption('status', null, InputOption::VALUE_NONE, 'Report how much is outstanding and exit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schema = $this->services->get(SearchIndexSchema::class);
        $indexer = $this->services->get(SearchIndexer::class);

        if ($input->getOption('status')) {
            if (!$schema->exists()) {
                $output->writeln('<comment>The search index does not exist. Run ./yeswicli migrate, or search:reindex --rebuild.</comment>');

                return Command::FAILURE;
            }
            $output->writeln($indexer->pending() . ' Content(s) queued for reindexing.');

            return Command::SUCCESS;
        }

        if ($input->getOption('rebuild')) {
            $output->writeln('Dropping and recreating the index tables...');
            $schema->recreate();
        } elseif (!$schema->exists()) {
            $output->writeln('Creating the index tables...');
            $schema->create();
        }

        if ($input->getOption('rebuild') || $input->getOption('all')) {
            $output->writeln($indexer->enqueueEverything() . ' Content(s) queued.');
        }

        $form = (string)$input->getOption('form');
        if ($form !== '') {
            $output->writeln($indexer->enqueueForm($form) . " entrie(s) of form {$form} queued.");
        }

        $output->writeln('<info>' . $indexer->pending() . ' Content(s) waiting in the background queue; run ./yeswicli messenger:drain to reindex them now.</info>');

        return Command::SUCCESS;
    }
}
