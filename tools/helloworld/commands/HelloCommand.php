<?php

namespace YesWiki\Helloworld\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Wiki;

class HelloCommand extends Command
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
            // the name of the command : ./yeswicli helloworld:hello"
            ->setName('helloworld:hello')
            // the short description shown while running "./yeswicli list"
            ->setDescription('Display message "Hello !".')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp("This command display the message \"Hello !\" with options for uppercase of add a username.\n" .
                "The argument \"username\" can be used to add a username. Example : \n" .
                "Command line'./yeswicli helloworld:hello \"John Smith\"' gives \"Hello John Smith !\"")

            // add argument for username
            // second parameter could be InputArgument::OPTIONAL <=> null, InputArgument::REQUIRED, InputArgument::IS_ARRAY
            // third parameter is the description
            // forth parameter is default value
            ->addArgument('username', InputArgument::OPTIONAL, 'Username')

            // add option to display output as UPPERCASE
            // second parameter null|string is shortcut
            // third parameter null|int could be InputOption::VALUE_NONE <=> null, InputOption::VALUE_REQUIRED
            //          , InputOption::VALUE_OPTIONAL, InputOption::VALUE_IS_ARRAY, InputOption::VALUE_NEGATABLE
            // forth parameter is the description
            ->addOption('uppercase', 'u', InputOption::VALUE_NONE, 'Display output in UPPERCASE')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $username = $input->getArgument('username');
        $username = empty($username) ? '' : "$username ";
        $outputString = "Hello $username!";
        if ($input->getOption('uppercase')) {
            $outputString = strtoupper($outputString);
        }
        $output->writeln($outputString);

        return Command::SUCCESS;
    }
}
