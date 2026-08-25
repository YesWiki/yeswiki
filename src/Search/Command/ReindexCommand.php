<?php

namespace YesWiki\Search\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\SearchIndexSchema;

/** `./yeswicli search:reindex` -- the one way the search index is (re)built (ticket 18). */
class ReindexCommand extends Command
{
    private const LOCK_FILE = 'cache/search-reindex.lock';
    private const LOCK_HELD_ELSEWHERE = 'held';
    private const LOCK_UNAVAILABLE = 'unavailable';

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
            ->addOption('drain', null, InputOption::VALUE_NONE, 'Reindex whatever is queued and stop')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Queue every Content first')
            ->addOption('rebuild', null, InputOption::VALUE_NONE, 'Drop and recreate the index tables, then queue everything')
            ->addOption('form', null, InputOption::VALUE_REQUIRED, 'Queue one form\'s entries first')
            ->addOption('batch', null, InputOption::VALUE_REQUIRED, 'Contents per pass', '500')
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

        $lock = $this->acquireLock();
        if ($lock === self::LOCK_HELD_ELSEWHERE) {
            $output->writeln('<comment>Another reindex is already running; leaving the queue to it.</comment>');

            return Command::SUCCESS;
        }

        try {
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

            $batch = max(1, (int)$input->getOption('batch'));
            $total = 0;
            $remaining = $indexer->pending();
            while (true) {
                $done = $indexer->drain($batch);
                if ($done === 0) {
                    break;
                }
                $total += $done;
                $remaining = $indexer->pending();
                $output->writeln("  ... {$total} reindexed, {$remaining} to go");
            }

            $output->writeln("<info>Reindexed {$total} Content(s). Queue is " . ($remaining === 0 ? 'empty' : "at {$remaining}") . '.</info>');

            return Command::SUCCESS;
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * @return resource|string the held lock; LOCK_HELD_ELSEWHERE if another run has it;
     *                         LOCK_UNAVAILABLE to proceed without one
     */
    private function acquireLock()
    {
        $locks = $this->services->get(RuntimeLock::class);

        $handle = $locks->acquire(self::LOCK_FILE);
        if ($handle === null) {
            return self::LOCK_UNAVAILABLE;
        }
        if (!$locks->tryLock($handle)) {
            $locks->release($handle);

            return self::LOCK_HELD_ELSEWHERE;
        }

        return $handle;
    }

    /**
     * @param resource|string $handle
     */
    private function releaseLock($handle): void
    {
        $this->services->get(RuntimeLock::class)->release($handle);
    }
}
