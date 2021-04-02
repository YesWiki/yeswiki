<?php
namespace YesWiki\AutoUpdate\Commands;

use Symfony\Component\Console\Command\Command;
// use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
// use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
// use Symfony\Component\Console\Question\ChoiceQuestion;
// use YesWiki\Bazar\Service\EntryManager;
// use YesWiki\Core\Service\PageManager;
use YesWiki\Wiki;

class UpdaterCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'updater:update';

    protected $wiki;

    public function __construct(Wiki &$wiki)
    {
        parent::__construct();
        $this->wiki = $wiki;
    }

    protected function configure()
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Update package for yeswiki core, extension or theme.')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('This command allows you to update package for yeswiki core, extension or theme and execute postupgrade.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('TODO ;)');
        return Command::SUCCESS;
    }
}
