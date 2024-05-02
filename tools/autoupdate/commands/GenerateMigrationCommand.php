<?php

namespace YesWiki\AutoUpdate\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Wiki;

class GenerateMigrationCommand extends Command
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
      ->setName('generate:migration')
      ->setDescription('Create a new migration file')
      ->addArgument('className', InputArgument::REQUIRED, 'The name of the migration class (CamelCase)');
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $className = $input->getArgument('className');
    $timestamp = date('YmdHis');
    $migrationFileName = $timestamp . '_' . $className . '.php';
    $migrationTemplate = "<?php\n\nuse YesWiki\\Core\\YesWikiMigration;\n\nclass $className extends YesWikiMigration\n{\n    public function run()\n    {\n\n    }\n}";

    $filePath = 'includes/migrations/' . $migrationFileName;
    if (!file_exists($filePath)) {
      file_put_contents($filePath, $migrationTemplate);
      $output->writeln("Migration file created successfully: $filePath");
      return Command::SUCCESS;
    } else {
      $output->writeln("Error: Migration file already exists: $filePath");
      return Command::FAILURE;
    }
  }
}