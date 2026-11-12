<?php

namespace YesWiki\Render\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Render\Service\TemplateImporter;

/** `./yeswicli templates:export <folder>`: writes the database's template overrides out as files, for git or an image built FROM the published one (ticket 18). */
class TemplatesExportCommand extends Command
{
    public function __construct(private readonly ContainerInterface $services)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('templates:export')
            ->setDescription('Write the template overrides the database holds as .twig files, at their paths under a folder')
            ->addArgument('folder', InputArgument::REQUIRED, 'Where to write them')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List what would be written; write nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $folder = (string)$input->getArgument('folder');
        $folder = str_starts_with($folder, '/') ? $folder : getcwd() . '/' . $folder;
        $dryRun = (bool)$input->getOption('dry-run');
        $written = $this->services->get(TemplateImporter::class)->exportTo($folder, $dryRun);

        foreach ($written as $path) {
            $output->writeln("  $folder/$path");
        }
        $output->writeln(sprintf('<info>%d template override(s) %s.</info>', count($written), $dryRun ? 'would be written' : 'written'));

        return Command::SUCCESS;
    }
}
