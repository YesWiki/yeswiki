<?php

namespace YesWiki\AutoUpdate\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
            ->addArgument('className', InputArgument::REQUIRED, 'The name of the migration class (CamelCase)')
            ->addOption('tool', 't', InputOption::VALUE_REQUIRED, 'The name of the tool (otherwise migration created in root folder)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $tool = $input->getOption('tool');
        $className = (!empty($tool) ? ucwords(strtolower($tool)) : '') . $input->getArgument('className');
        $timestamp = date('YmdHis');
        $migrationFileName = $timestamp . '_' . $className . '.php';
        $migrationTemplate = "<?php\n\nuse YesWiki\\Core\\YesWikiMigration;\n\nclass $className extends YesWikiMigration\n{\n    public function run()\n    {\n\n    }\n}";

        $folderPath = (!empty($tool) ? "tools/$tool/migrations/" : 'includes/migrations/');
        if (!file_exists($folderPath)) {
            mkdir($folderPath);
        }
        $filePath = $folderPath . $migrationFileName;

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
