<?php

namespace YesWiki\Core\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Core\Service\ArchiveService;
use YesWiki\Wiki;

class ArchiveCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'core:archive';

    protected $archiveService;
    protected $wiki;

    public function __construct(Wiki &$wiki)
    {
        parent::__construct();
        $this->archiveService = $wiki->services->get(ArchiveService::class);
        $this->wiki = $wiki;
    }

    protected function configure()
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Create archive of the YesWiki.')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp("Create archive of the YesWiki.\n".
                "To not save files use '--nosavefiles'\n".
                "To not save database use '--nosavedatabase'\n")
            
            ->addOption('nosavefiles', 'd', InputOption::VALUE_NONE, 'Do not save files of the wiki')
            ->addOption('nosavedatabase', 'f', InputOption::VALUE_NONE, 'Do not save database')
            ->addOption('extrafiles', 'e', InputOption::VALUE_REQUIRED, 'Extrafiles, path relative to root, coma separated')
            ->addOption('excludedfiles', 'x', InputOption::VALUE_REQUIRED, 'Excludedfiles, path relative to root, coma separated')
            ->addOption('anonymous', 'a', InputOption::VALUE_REQUIRED, 'Params to anonymize in wakka.config.php, json_encoded')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $nosavefiles = $input->getOption('nosavefiles');
        $nosavedatabase = $input->getOption('nosavedatabase');

        if ($nosavefiles && $nosavedatabase) {
            $output->writeln("Invalid options : It is not possible to use --nosavefiles and --nosavedatabase options in same time.");
            return Command::INVALID;
        }

        $extrafiles = $this->prepareFileList($input->getOption('extrafiles'));
        $excludedfiles = $this->prepareFileList($input->getOption('excludedfiles'));
        $rawAnonymous = $input->getOption('anonymous');
        $anonymous = null;
        if (!empty($rawAnonymous)) {
            $rawAnonymous = json_decode($rawAnonymous, true);
            if (is_array($rawAnonymous)) {
                $anonymous = $rawAnonymous;
            }
        }

        $location = $this->archiveService->archive($output, !$nosavefiles, !$nosavedatabase, $extrafiles, $excludedfiles, $anonymous);

        return Command::SUCCESS;
    }

    private function prepareFileList($list): array
    {
        if (empty($list)) {
            $list = [];
        } else {
            $list = array_filter(array_map('trim', explode(',', $list)));
        }
        return $list;
    }
}
