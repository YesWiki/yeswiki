<?php

namespace YesWiki\Core\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Core\Service\AutoUpdateService;
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $package = $input->getArgument('package');

        $updateService = $this->wiki->services->get(AutoUpdateService::class);

        // ADR-0007: on a farm, only the instance sharing YESWIKI_SOURCE_DIR as its own
        // YESWIKI_INSTANCE_DIR may trigger an upgrade -- a satellite instance running this
        // command would otherwise silently mutate the shared source out from under every
        // other farm instance.
        if (!$updateService->isDesignatedUpdateInstance()) {
            $output->writeln('<error>' . _t('AU_NOT_DESIGNATED_UPDATE_INSTANCE') . '</error>');

            return Command::FAILURE;
        }

        $output->writeln("Starting Upgrading $package");

        $updateService->initRepository();
        $messages = $updateService->upgrade($package);

        foreach ($messages as $message) {
            $output->writeln("{$message['status']} | {$message['text']}");
        }

        return Command::SUCCESS;
    }
}
