<?php

namespace YesWiki\Kernel\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\EnvFile;
use YesWiki\Kernel\Service\LegacyConfigurationFile;

/** Prints the private/.env lines that hold the host's settings a configuration file still carries (replicas 20). */
class ConfigExportEnvCommand extends Command implements RunsOutsideAnInstance
{
    public function __construct(protected ?ContainerInterface $services = null)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('config:export-env')
            ->setDescription('Print the private/.env lines for the host settings yeswiki.config.php still holds.')
            ->setHelp("Put what it prints in private/.env, then remove yeswiki.config.php: the wiki's own settings are already in its database.\n"
                . 'Settings the environment or private/.env already state are left out.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = new LegacyConfigurationFile(ConfigurationFileProvider::getConfigFileFromEnv());
        if (!$file->exists()) {
            $output->writeln('<info>No ' . basename($file->path) . ' here: nothing to export.</info>', OutputInterface::VERBOSITY_NORMAL);

            return Command::SUCCESS;
        }
        $output->write((new EnvFile())->contentsFor($file->hostSettingsToMove(YESWIKI_INSTANCE_DIR)), false, OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}
