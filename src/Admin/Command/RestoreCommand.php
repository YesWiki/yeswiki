<?php

namespace YesWiki\Admin\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Admin\Service\ArchiveService;

/** The other half of core:archive: puts one back. Runs detached when the admin screen starts it, so a large restore is not cut short by a request timeout. */
class RestoreCommand extends Command
{
    protected ArchiveService $archiveService;

    public function __construct(ContainerInterface $services)
    {
        parent::__construct();
        $this->archiveService = $services->get(ArchiveService::class);
    }

    protected function configure()
    {
        $this
            ->setName('core:restore')

            ->setDescription('Restore an archive of the YesWiki.')

            ->setHelp("Restore an archive of the YesWiki.\n" .
                "The archive is named by its filename in the backups folder, with '--archive'.\n" .
                "To restore only the database use '--database-only'\n" .
                "To restore only the files use '--files-only'\n" .
                "To leave the addresses the backup carries as they are use '--keep-urls'\n")

            ->addOption('archive', 'a', InputOption::VALUE_REQUIRED, 'Filename of the archive to restore')
            ->addOption('database-only', 'd', InputOption::VALUE_NONE, 'Restore only the database of the YesWiki')
            ->addOption('files-only', 'f', InputOption::VALUE_NONE, 'Restore only the files of the YesWiki')
            ->addOption('keep-urls', 'k', InputOption::VALUE_NONE, 'Keep the addresses stored in the backup instead of pointing them at this wiki')
            ->addOption('uid', 'u', InputOption::VALUE_REQUIRED, 'uid to retrieve input and output files')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filename = $input->getOption('archive');
        if (!is_string($filename) || trim($filename) === '') {
            $output->writeln('Missing option : --archive names the archive to restore.');

            return Command::INVALID;
        }

        $databaseOnly = (bool)$input->getOption('database-only');
        $filesOnly = (bool)$input->getOption('files-only');
        if ($databaseOnly && $filesOnly) {
            $output->writeln('Invalid options : It is not possible to use --database-only and --files-only options in same time.');

            return Command::INVALID;
        }

        $uid = $input->getOption('uid');
        $uid = is_string($uid) ? $uid : '';

        try {
            $this->archiveService->synchronously()->restoreArchive(
                $filename,
                !$databaseOnly,
                !$filesOnly,
                !$input->getOption('keep-urls'),
                $uid
            );
        } catch (\Throwable $th) {
            $output->writeln('Restore failed : ' . $th->getMessage());

            return Command::FAILURE;
        }

        $output->writeln("Archive \"$filename\" successfully restored !");

        return Command::SUCCESS;
    }
}
