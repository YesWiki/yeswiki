<?php

namespace YesWiki\AutoUpdate\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\AutoUpdate\Service\AutoUpdateService;
use YesWiki\Wiki;

class UpgradeCommand extends Command
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
            ->setName('upgrade')
            ->addArgument('package', InputArgument::OPTIONAL, 'Specific extension or theme', 'yeswiki')
            ->setDescription('Upgrade the wiki, or a specific extension if package name is provided');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $package = $input->getArgument('package');

        $output->writeln("Starting Upgrading $package");

        $updateService = $this->wiki->services->get(AutoUpdateService::class);
        $updateService->initRepository();
        $messages = $updateService->upgrade($package);

        foreach ($messages as $message) {
            $output->writeln("{$message['status']} | {$message['text']}");
        }

        return Command::SUCCESS;
    }
}
