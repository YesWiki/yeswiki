<?php

namespace YesWiki\Render\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Render\Service\TemplateImporter;

/** `./yeswicli templates:import [folder]`: turns `.twig` files into the template overrides the screens edit (ticket 18). */
class TemplatesImportCommand extends Command
{
    public function __construct(private readonly ContainerInterface $services)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('templates:import')
            ->setDescription('Save the .twig files of a folder as template overrides in the database, at the same paths')
            ->addArgument('folder', InputArgument::OPTIONAL, 'The folder to read, its paths naming the overrides', 'custom/templates')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Replace an override the database already holds')
            ->addOption('keep-files', null, InputOption::VALUE_NONE, 'Leave the imported files where they are')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Say what would be imported, skipped and deleted; write nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $folder = (string)$input->getArgument('folder');
        $folder = str_starts_with($folder, '/') ? $folder : YESWIKI_INSTANCE_DIR . '/' . $folder;
        if (!$this->services->get(LocalFiles::class)->isDirectory($folder)) {
            $output->writeln("<error>No folder at $folder</error>");

            return Command::FAILURE;
        }
        $dryRun = (bool)$input->getOption('dry-run');
        $report = $this->services->get(TemplateImporter::class)->importFrom($folder, (bool)$input->getOption('force'), (bool)$input->getOption('keep-files'), $dryRun);

        foreach ($report[TemplateImporter::IMPORTED] as $path) {
            $output->writeln("  imported  $path");
        }
        foreach ($report[TemplateImporter::SKIPPED] as $path) {
            $output->writeln("  skipped   $path, already in the database (--force replaces it)");
        }
        foreach ($report[TemplateImporter::INVALID] as $path => $error) {
            $output->writeln("  <error>invalid</error>   $path: $error");
        }
        foreach ($report[TemplateImporter::DELETED] as $path) {
            $output->writeln("  deleted   $path");
        }
        foreach ($report[TemplateImporter::KEPT] as $path) {
            $output->writeln("  kept      $path, read-only: the database's copy now hides it");
        }
        $output->writeln(sprintf(
            '<info>%d imported, %d skipped, %d invalid.%s</info>',
            count($report[TemplateImporter::IMPORTED]),
            count($report[TemplateImporter::SKIPPED]),
            count($report[TemplateImporter::INVALID]),
            $dryRun ? ' Nothing was written.' : ''
        ));

        return $report[TemplateImporter::INVALID] === [] ? Command::SUCCESS : Command::FAILURE;
    }
}
