<?php

// core:exportdb, the mysqldump export of the wiki's own tables and its connection test.

namespace YesWiki\Core\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\ConsoleService;
use YesWiki\Wiki;

class DbCommand extends Command
{
    private const DUMP_EXECUTABLES = ['mariadb-dump', 'mysqldump'];
    private const DUMP_VERSION_PATTERN = '#^(?:.*[/\\\\])?(?:mysqldump|mariadb-dump)(?:\.exe)?\s+(?:Ver\s*\d|from\s+\d)#i';

    protected $consoleService;
    protected $params;
    protected $wiki;

    public function __construct(Wiki &$wiki)
    {
        parent::__construct();
        $this->consoleService = $wiki->services->get(ConsoleService::class);
        $this->params = $wiki->services->get(ParameterBagInterface::class);
        $this->wiki = $wiki;
    }

    protected function configure()
    {
        $this
            ->setName('core:exportdb')
            ->setDescription('Manage database of the YesWiki.')
            ->setHelp("Manage database of the YesWiki.\n" .
                "To test use '--test'\n")
            ->addOption('test', 't', InputOption::VALUE_NONE, 'Test the connection to mysqldump (return OK/NOK)')
            ->addOption('filepath', 'f', InputOption::VALUE_REQUIRED, '.sql file path where export db')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isTest = $input->getOption('test');
        $filepath = $input->getOption('filepath');

        if (!$isTest && (empty($filepath) || substr($filepath, -4) != '.sql')) {
            $output->writeln("Invalid options : option '--filepath' is required and should end by '.sql' if not testing.");

            return Command::INVALID;
        }

        if ($isTest) {
            return $this->test($output);
        }

        return $this->export($output, $filepath);
    }

    // Reads the connection settings from wakka.config.php, as hostArg, databasename, tablePrefix, username and password.
    private function getDbParams(): array
    {
        $hostname = $this->params->get('mysql_host');
        $this->assertParamIsNotEmptyString('mysql_host', $hostname);
        if (strpos($hostname, ':') !== false) {
            list($hostname, $port) = explode(':', $hostname);
        }
        if (!empty($port) && strval(intval($port)) == strval($port)) {
            $hostArg = ["--host=$hostname", "--port=$port"];
        } else {
            $hostArg = ["--host=$hostname"];
        }

        $databasename = $this->params->get('mysql_database');
        $this->assertParamIsNotEmptyString('mysql_database', $databasename);

        $tablePrefix = $this->params->get('table_prefix');
        $this->assertParamIsNotEmptyString('table_prefix', $tablePrefix);

        $username = $this->params->get('mysql_user');
        $this->assertParamIsString('mysql_user', $username);

        $password = $this->params->get('mysql_password');
        $this->assertParamIsString('mysql_password', $password);

        return compact(['hostArg', 'databasename', 'tablePrefix', 'username', 'password']);
    }

    // Dumps the wiki's own tables into $filepath.
    private function export(OutputInterface $output, string $filepath): int
    {
        $realFilePath = realpath(dirname($filepath)) . DIRECTORY_SEPARATOR . basename($filepath);
        extract($this->getDbParams());
        try {
            $results = $this->consoleService->findAndStartExecutableSync(
                $this->findDumpExecutable() ?? 'mysqldump',
                array_merge(
                    $hostArg,
                    [
                        "--user=$username",
                        "--password=$password",
                        "--result-file=$realFilePath",
                        $databasename,
                        "{$tablePrefix}users",
                        "{$tablePrefix}pages",
                        "{$tablePrefix}nature",
                        "{$tablePrefix}triples",
                        "{$tablePrefix}acls",
                        "{$tablePrefix}links",
                        "{$tablePrefix}referrers",
                    ]
                ),
                '',
                $this->getExtaDirs(),
                120
            );
            $err = $this->getErr($results);
            try {
                $fileContent = file_get_contents($realFilePath);
            } catch (\Throwable $th) {
                $fileContent = '';
            }
            if (!empty($fileContent)) {
                return Command::SUCCESS;
            } elseif (!empty($err)) {
                $output->writeln($err);
            }
        } catch (\Throwable $ex) {
            $output->writeln("System error when testing mysqldump : {$ex->getMessage()}");
        }

        return Command::FAILURE;
    }

    // Checks that mysqldump runs and reaches the wiki's pages table, without walking the other tables of a shared database.
    private function test(OutputInterface $output): int
    {
        extract($this->getDbParams());
        try {
            $dump = $this->findDumpExecutable();
            if ($dump !== null) {
                $results = $this->consoleService->findAndStartExecutableSync(
                    $dump,
                    array_merge(
                        $hostArg,
                        [
                            "--user=$username",
                            "--password=$password",
                            '-t',
                            '-d',
                            $databasename,
                            "{$tablePrefix}pages",
                        ]
                    ),
                    '',
                    $this->getExtaDirs(),
                    10
                );
                $outputResult = $this->getOutput($results);
                if (empty($outputResult)) {
                    throw new \Exception('output should not be empty during test to connect to database via mysql');
                }
                $output->writeln('OK');

                return Command::SUCCESS;
            }
        } catch (\Throwable $ex) {
            $output->writeln('System error when testing mysqldump : ' . $this->wiki->dumpThrowable($ex));
        }
        $output->writeln('NOK');

        return Command::FAILURE;
    }

    // The first dump program found whose -V output is a known version line, mariadb-dump before its deprecated mysqldump alias.
    private function findDumpExecutable(): ?string
    {
        foreach (self::DUMP_EXECUTABLES as $name) {
            try {
                $results = $this->consoleService->findAndStartExecutableSync($name, ['-V'], '', $this->getExtaDirs(), 10);
            } catch (\Throwable $th) {
                continue;
            }
            if (preg_match(self::DUMP_VERSION_PATTERN, $this->getOutput($results))) {
                return $name;
            }
        }

        return null;
    }

    private function getOutput($results): string
    {
        $outputResult = (!empty($results) && is_array($results)) ? ($results[array_key_first($results)]['stdout'] ?? '') : '';

        return $outputResult;
    }

    private function getErr($results): string
    {
        return (!empty($results) && is_array($results)) ? ($results[array_key_first($results)]['stderr'] ?? '') : '';
    }

    private function getExtaDirs(): array
    {
        return '\\' === DIRECTORY_SEPARATOR ? ['c:\\xampp\\mysql\\bin\\'] : ['/usr/bin/', '/usr/local/bin/'];
    }

    // Throws when a configuration value is empty or not a string.
    protected function assertParamIsNotEmptyString(string $name, $param)
    {
        if (empty($param)) {
            throw new \Exception("'$name' should not be empty in 'wakka.config.php'");
        }
        $this->assertParamIsString($name, $param);
    }

    // Throws when a configuration value is not a string.
    protected function assertParamIsString(string $name, $param)
    {
        if (!is_string($param)) {
            throw new \Exception("'$name' should be a string in 'wakka.config.php'");
        }
    }
}
