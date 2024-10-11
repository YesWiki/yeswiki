<?php

namespace YesWiki\Core\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Core\Service\ConsoleService;
use YesWiki\Wiki;

class TestConsoleServiceCommand extends Command
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
            ->setName('core:testconsoleservice')
            // the short description shown while running "./yeswicli list"
            ->setDescription('Offer tests for ConsoleService.')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp("This command offers tests for ConsoleService.\nIf the option --childtext is empty, wait 2 seconds before appending text.")

            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Filename where append text')
            ->addOption('text', 't', InputOption::VALUE_REQUIRED, 'Text to append')
            ->addOption('childtext', 'c', InputOption::VALUE_OPTIONAL, 'Text to append for child')
            ->addOption('wait', 'w', InputOption::VALUE_REQUIRED, 'Time to wait (s)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file = $input->getOption('file');
        if (!empty($file)) {
            $file = basename($file);
        }
        $text = $input->getOption('text');
        $wait = abs(intval($input->getOption('wait')));
        // force file to be in cache folder
        if (empty($file) || empty($text) || is_dir("cache/$file") || empty($wait)) {
            $output->writeln([
                '',
                'ERROR : required arguments are missing (file or text or wait).',
                'To get some help use : yeswicli core:testconsoleservice --help',
                '',
            ]);

            return Command::FAILURE;
        }
        $childtext = $input->getOption('childtext');
        if (empty($childtext)) {
            sleep($wait);
            $this->writeToFile("cache/$file", $text);
            exit();
        } else {
            $consoleService = $this->wiki->services->get(ConsoleService::class);
            $consoleService->startConsoleAsync('core:testconsoleservice', ['-f', $file, '-t', $childtext, '-w', $wait]);
            $this->writeToFile("cache/$file", $text);
            exit();
        }

        return Command::SUCCESS;
    }

    private function writeToFile(string $file, string $content)
    {
        file_put_contents($file, $content, FILE_APPEND);
    }
}
