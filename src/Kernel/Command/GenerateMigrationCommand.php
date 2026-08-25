<?php

namespace YesWiki\Kernel\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Files\Service\LocalFiles;

class GenerateMigrationCommand extends Command
{
    protected ContainerInterface $services;

    public function __construct(ContainerInterface $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    /** Resolved rather than injected: `src/commands/console` builds commands with the container alone. */
    private function localFiles(): LocalFiles
    {
        return $this->services->get(LocalFiles::class);
    }

    protected function configure()
    {
        $this
            ->setName('generate:migration')
            ->setDescription('Create a new migration file')
            ->addArgument('className', InputArgument::REQUIRED, 'The name of the migration class (CamelCase)')
            ->addOption('tool', 't', InputOption::VALUE_REQUIRED, 'The name of the tool (otherwise migration created in root folder)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tool = $input->getOption('tool');
        $className = (!empty($tool) ? ucwords(strtolower($tool)) : '') . $input->getArgument('className');
        $timestamp = date('YmdHis');
        $migrationFileName = $timestamp . '_' . $className . '.php';
        $migrationTemplate = "<?php\n\nuse YesWiki\\Core\\YesWikiMigration;\n\nclass $className extends YesWikiMigration\n{\n    public function run()\n    {\n\n    }\n}";

        $folderPath = (!empty($tool) ? "extensions/$tool/migrations/" : 'src/migrations/');
        if (!$this->localFiles()->isDirectory($folderPath)) {
            $this->localFiles()->makeDirectory($folderPath);
        }
        $filePath = $folderPath . $migrationFileName;

        if (!$this->localFiles()->exists($filePath)) {
            $this->localFiles()->write($filePath, $migrationTemplate);
            $output->writeln("Migration file created successfully: $filePath");

            return Command::SUCCESS;
        }
        $output->writeln("Error: Migration file already exists: $filePath");

        return Command::FAILURE;
    }
}
