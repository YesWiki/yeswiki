<?php

namespace YesWiki\Admin\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Admin\Service\Onboarding;

/** `./yeswicli onboarding:apply` -- the onboarding screen's choice, made from a terminal. */
class OnboardingCommand extends Command
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
            ->setName('onboarding:apply')
            ->setDescription('Create the chosen Starters and the home page of a fresh wiki')
            ->setHelp("With no starter named, the wiki starts empty. --all creates every Starter the Program ships.\n"
                . 'Does nothing once the home page exists.')
            ->addArgument('starters', InputArgument::IS_ARRAY, 'Starter slugs, e.g. annuaire agenda')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Create every Starter');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $onboarding = $this->services->get(Onboarding::class);

        if (!$onboarding->isPending()) {
            $output->writeln('<comment>The home page already exists; nothing to do.</comment>');

            return Command::SUCCESS;
        }

        /** @var list<string> $slugs */
        $slugs = $input->getOption('all') ? array_keys($onboarding->starters()) : $input->getArgument('starters');

        try {
            $onboarding->apply($slugs);
        } catch (\InvalidArgumentException $unknown) {
            $output->writeln('<error>' . $unknown->getMessage() . '. Known: ' . implode(', ', array_keys($onboarding->starters())) . '</error>');

            return Command::INVALID;
        }

        $output->writeln('<info>' . ($slugs === [] ? 'Empty wiki created.' : 'Created: ' . implode(', ', $slugs) . '.') . '</info>');

        return Command::SUCCESS;
    }
}
