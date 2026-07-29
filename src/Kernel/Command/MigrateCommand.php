<?php

namespace YesWiki\Kernel\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Kernel\Service\MigrationService;

class MigrateCommand extends Command
{
    protected ContainerInterface $services;

    public function __construct(ContainerInterface $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    protected function configure()
    {
        $this
            ->setName('migrate')
            ->setDescription('Run all pending migrations (after an upgrade)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting migrations');

        $messages = $this->services->get(MigrationService::class)->run();
        if (count($messages) == 0) {
            $output->writeln('No migrations to run');
        }

        foreach ($messages as $message) {
            $output->writeln("{$message['status']} | {$message['text']}");
        }

        return Command::SUCCESS;
    }
}
