<?php

namespace YesWiki\Admin\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Admin\Service\ArchiveService;

class ArchiveCommand extends Command
{
    protected ArchiveService $archiveService;
    protected ContainerInterface $services;

    public function __construct(ContainerInterface $services)
    {
        parent::__construct();
        $this->archiveService = $services->get(ArchiveService::class);
        $this->services = $services;
    }

    protected function configure()
    {
        $this
            ->setName('core:archive')

            ->setDescription('Create archive of the YesWiki.')

            ->setHelp("Create archive of the YesWiki.\n" .
                "To save only the database use '--database-only'\n" .
                "To save only the files use '--files-only'\n")

            ->addOption('database-only', 'd', InputOption::VALUE_NONE, 'Save only the database of the YesWiki')
            ->addOption('files-only', 'f', InputOption::VALUE_NONE, 'Save only the files of the YesWiki')
            ->addOption('foldersToInclude', 'i', InputOption::VALUE_REQUIRED, 'Folders to include, path relative to root, coma separated')
            ->addOption('foldersToExclude', 'x', InputOption::VALUE_REQUIRED, 'Folders to exclude, path relative to root, coma separated')
            ->addOption('hideConfigValues', 'a', InputOption::VALUE_REQUIRED, 'Params to anonymize in yeswiki.config.php, json_encoded')
            ->addOption('uid', 'u', InputOption::VALUE_REQUIRED, 'uid to retrive input and ouput files')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ob_start();

        $databaseOnly = $input->getOption('database-only');
        $filesOnly = $input->getOption('files-only');

        if ($databaseOnly && $filesOnly) {
            $output->writeln('Invalid options : It is not possible to use --database-only and --files-only options in same time.');

            return Command::INVALID;
        }

        $foldersToInclude = $this->prepareFileList($input->getOption('foldersToInclude'));
        $foldersToExclude = $this->prepareFileList($input->getOption('foldersToExclude'));
        $rawHideConfigValues = $input->getOption('hideConfigValues');
        $hideConfigValues = null;
        if (!empty($rawHideConfigValues)) {
            $rawHideConfigValues = json_decode($rawHideConfigValues, true);
            if (is_array($rawHideConfigValues)) {
                $hideConfigValues = $rawHideConfigValues;
            }
        }
        $uid = $input->getOption('uid');
        $uid = empty($uid) ? '' : $uid;

        $location = $this->archiveService->synchronously()->archive($output, !$databaseOnly, !$filesOnly, $foldersToInclude, $foldersToExclude, $hideConfigValues, $uid);

        ob_end_clean();

        return Command::SUCCESS;
    }

    /**
     * @param mixed $list the raw option value: a comma-separated list, or null when unset
     *
     * @return list<string>
     */
    private function prepareFileList($list): array
    {
        if (!is_string($list) || trim($list) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $list))));
    }
}
