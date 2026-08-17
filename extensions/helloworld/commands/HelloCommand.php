<?php

namespace YesWiki\Helloworld\Commands;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class HelloCommand extends Command
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

            ->setName('helloworld:hello')

            ->setDescription('Display message "Hello !".')

            ->setHelp("This command display the message \"Hello !\" with options for uppercase of add a username.\n" .
                "The argument \"username\" can be used to add a username. Example : \n" .
                "Command line'./yeswicli helloworld:hello \"John Smith\"' gives \"Hello John Smith !\"")

            ->addArgument('username', InputArgument::OPTIONAL, 'Username')

            ->addOption('uppercase', 'u', InputOption::VALUE_NONE, 'Display output in UPPERCASE')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
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
