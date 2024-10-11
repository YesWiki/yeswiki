<?php

namespace YesWiki\AutoUpdate\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\AutoUpdate\Service\MigrationService;
use YesWiki\Wiki;

class MigrateCommand extends Command
{
    protected $wiki;

    public function __construct(Wiki &$wiki)
    {
        parent::__construct();
        $this->wiki = $wiki;
    }

    protected function configure()
    {
        $this
            ->setName('migrate')
            ->setDescription('Run all pending migrations (after an upgrade)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Starting migrations');

        $messages = $this->wiki->services->get(MigrationService::class)->run();
        if (count($messages) == 0) {
            $output->writeln('No migrations to run');
        }

        foreach ($messages as $message) {
            $output->writeln("{$message['status']} | {$message['text']}");
        }

        return Command::SUCCESS;
    }
}
