<?php

namespace YesWiki\Import\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Import\Service\ImporterManager;
use YesWiki\Import\Service\SyncScheduler;

/**
 * `./yeswicli importer:sync` -- imports the data sources declared in `dataSources`.
 *
 * Every other way of importing ends up here: the cron or webhook calling `/api/import/sync`,
 * the `{{sync}}` button, and the automatic sync, which spawns this command per source when
 * the wiki does its housekeeping. Each source's outcome is recorded as its last sync, so the
 * admin page can report it whichever of those triggered it.
 */
class ImporterCommand extends Command
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
            ->setName('importer:sync')
            ->setDescription('Sync selected data sources to this YesWiki.')
            ->setHelp('Synchronize selected data sources to this YesWiki.' . "\n" .
                "If no source indicated it will sync them all\n")
            ->addOption('source', 's', InputOption::VALUE_OPTIONAL, 'The key name in the config file for source, leave empty for all sources');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dataSources = $this->dataSources();
        if (empty($dataSources)) {
            $output->writeln('No data sources found in config, does dataSources contain something?');

            return Command::SUCCESS;
        }
        if (!$this->checkConfig($dataSources, $output)) {
            return Command::INVALID;
        }

        $source = $input->getOption('source');
        if ($source) {
            if (empty($dataSources[$source])) {
                $output->writeln("No data source with key \"{$source}\" found in config, does dataSources[\"{$source}\"] contain something?");

                return Command::SUCCESS;
            }
            $dataSources = [$source => $dataSources[$source]];
        } else {
            $output->writeln('Importing all sources');
        }

        foreach ($dataSources as $id => $sourceOptions) {
            $output->writeln("Importing source \"{$id}\"");
            $this->syncSource((string)$id, $sourceOptions, $output);
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $sourceOptions
     */
    private function syncSource(string $id, array $sourceOptions, OutputInterface $output): void
    {
        // the importers report their per-entry detail by echoing it: captured so it can be
        // both printed here and kept as this source's last-sync log
        ob_start();
        $result = $this->services->get(ImporterManager::class)->syncSource($id, $sourceOptions);
        $log = trim(ob_get_clean() . "\n" . $result);
        $output->writeln($log);
        $this->services->get(SyncScheduler::class)->recordRun($id, $log);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function dataSources(): array
    {
        $params = $this->services->get(ParameterBagInterface::class);
        $dataSources = $params->has('dataSources') ? $params->get('dataSources') : [];

        return is_array($dataSources) ? $dataSources : [];
    }

    /**
     * @param array<string, array<string, mixed>> $dataSources
     */
    private function checkConfig(array $dataSources, OutputInterface $output): bool
    {
        $importers = $this->services->get(ImporterManager::class)->getAvailableImporters();
        foreach ($dataSources as $id => $source) {
            if (empty($source['importer'])) {
                $output->writeln("The importer is missing for data source \"{$id}\"");

                return false;
            }
            if (!array_key_exists($source['importer'], $importers)) {
                $output->writeln("The importer \"{$source['importer']}\" was not found");

                return false;
            }
        }

        return true;
    }
}
