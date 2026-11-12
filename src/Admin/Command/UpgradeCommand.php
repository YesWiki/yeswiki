<?php

namespace YesWiki\Admin\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Admin\Service\AutoUpdateService;

class UpgradeCommand extends Command
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
            ->setName('upgrade')
            ->addArgument('packages', InputArgument::IS_ARRAY, 'Extensions or themes, several at once when an image is built', ['yeswiki'])
            ->setDescription('Upgrade the wiki, or install and upgrade the extensions and themes named');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $updateService = $this->services->get(AutoUpdateService::class);
        if ($updateService->isImmutable()) {
            $output->writeln('<error>' . _t('AU_PROGRAM_IMMUTABLE') . '</error>');

            return Command::FAILURE;
        }
        $updateService->initRepository();
        $failed = false;
        foreach ((array)$input->getArgument('packages') as $package) {
            $package = (string)$package;
            if (!$updateService->mayUpgrade($package)) {
                $output->writeln('<error>' . _t('AU_NOT_DESIGNATED_UPDATE_INSTANCE') . '</error>');

                return Command::FAILURE;
            }
            if ($updateService->isForbiddenExtension($package)) {
                $output->writeln('<error>' . _t('AU_EXTENSION_FORBIDDEN', ['name' => $package]) . '</error>');

                return Command::FAILURE;
            }

            $output->writeln("Starting Upgrading $package");
            $messages = $updateService->upgrade($package);
            foreach ($messages as $message) {
                $output->writeln("{$message['status']} | {$message['text']}");
            }
            $failed = $failed || $messages->hasError();
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
