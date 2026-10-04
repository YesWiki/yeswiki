<?php

namespace YesWiki\Kernel\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\ExecutableFinder;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Kernel\Database\DumpRewriter;
use YesWiki\Kernel\Service\ConsoleService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\ThrowableFormatter;

class DbCommand extends Command
{
    protected ConsoleService $consoleService;
    protected ParameterBagInterface $params;
    protected ContainerInterface $services;

    public function __construct(ContainerInterface $services)
    {
        parent::__construct();
        $this->consoleService = $services->get(ConsoleService::class);
        $this->params = $services->get(ParameterBagInterface::class);
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
            ->setName('core:exportdb')
            ->setDescription('Manage database of the YesWiki.')

            ->setHelp("Manage database of the YesWiki.\n" .
                "To test use '--test'\n")

            ->addOption('test', 't', InputOption::VALUE_NONE, 'Test the connection to mysqldump (return OK/NOK)')
            ->addOption('filepath', 'f', InputOption::VALUE_REQUIRED, '.sql file path where export db')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
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

    /**
     * The parameters mysqldump connects with.
     *
     * @return array{hostArg: list<string>, databasename: string, tablePrefix: string, username: string, password: string}
     *
     * @throws \Exception
     */
    private function getDbParams(): array
    {
        $hostname = $this->params->get('db_host');
        $this->assertParamIsNotEmptyString('db_host', $hostname);
        if (strpos($hostname, ':') !== false) {
            list($hostname, $port) = explode(':', $hostname);
        }
        if (!empty($port) && strval(intval($port)) == strval($port)) {
            $hostArg = ["--host=$hostname", "--port=$port"];
        } else {
            $hostArg = ["--host=$hostname"];
        }

        $databasename = $this->params->get('db_database');
        $this->assertParamIsNotEmptyString('db_database', $databasename);

        $tablePrefix = $this->params->get('table_prefix');
        $this->assertParamIsNotEmptyString('table_prefix', $tablePrefix);

        $username = $this->params->get('db_user');
        $this->assertParamIsString('db_user', $username);

        $password = $this->params->get('db_password');
        $this->assertParamIsString('db_password', $password);

        return compact(['hostArg', 'databasename', 'tablePrefix', 'username', 'password']);
    }

    /**
     * export db via mysqldump.
     *
     * @return int Command:code
     *
     * @throws \Exception
     * @throws \Throwable
     */
    private function export(OutputInterface $output, string $filepath): int
    {
        $realFilePath = $this->localFiles()->realPath(dirname($filepath)) . DIRECTORY_SEPARATOR . basename($filepath);
        [
            'hostArg' => $hostArg,
            'databasename' => $databasename,
            'tablePrefix' => $tablePrefix,
            'username' => $username,
            'password' => $password,
        ] = $this->getDbParams();
        try {
            $tables = $this->ownTables($tablePrefix);
            if ($tables === []) {
                $output->writeln("No table of this wiki starts with '$tablePrefix'.");

                return Command::FAILURE;
            }
            $result = $this->runDumpTool(
                array_merge(
                    $hostArg,
                    [
                        "--user=$username",
                        "--password=$password",
                        "--result-file=$realFilePath",
                        $databasename,
                    ],
                    $tables
                ),
                120
            );
            if ($result['exit'] === 0 && self::isDump($this->headOf($realFilePath))) {
                return Command::SUCCESS;
            }
            $output->writeln($result['stderr'] !== '' ? $result['stderr'] : 'The dump tool wrote no dump.');
        } catch (\Throwable $ex) {
            $output->writeln("System error when running mysqldump : {$ex->getMessage()}");
        }

        return Command::FAILURE;
    }

    /**
     * This wiki's tables, and none of another wiki whose prefix starts like this one's.
     *
     * @return list<string>
     */
    private function ownTables(string $tablePrefix): array
    {
        $dbService = $this->services->get(DbService::class);

        return DumpRewriter::ownTables($dbService->schema()->getTables(), trim($tablePrefix));
    }

    /**
     * test connection to mysqldump.
     *
     * @return int Command:code
     *
     * @throws \Throwable
     */
    private function test(OutputInterface $output): int
    {
        [
            'hostArg' => $hostArg,
            'databasename' => $databasename,
            'tablePrefix' => $tablePrefix,
            'username' => $username,
            'password' => $password,
        ] = $this->getDbParams();
        try {
            $version = $this->runDumpTool(['-V'], 10);
            if ($version['exit'] === 0 && self::isDumpToolVersion($version['stdout'])) {
                $result = $this->runDumpTool(
                    array_merge(
                        $hostArg,
                        [
                            "--user=$username",
                            "--password=$password",
                            '-t',
                            '-d',
                            $databasename,
                        ]
                    ),
                    10
                );
                if ($result['exit'] !== 0 || !self::isDump($result['stdout'])) {
                    throw new \Exception('the dump tool could not reach the database: ' . trim($result['stderr']));
                }
                $output->writeln('OK');

                return Command::SUCCESS;
            }
        } catch (\Throwable $ex) {
            $output->writeln('System error when testing mysqldump : ' . $this->services->get(ThrowableFormatter::class)->dump($ex));
        }
        $output->writeln('NOK');

        return Command::FAILURE;
    }

    /** Whether a tool's `-V` line is that of mysqldump or mariadb-dump, from MySQL or MariaDB. */
    public static function isDumpToolVersion(string $version): bool
    {
        return preg_match('/(?:^|[\\\\\/])(?:mariadb-dump|mysqldump)(?:\.exe)?\s+(?:Ver|from)\s+\d/im', $version) === 1;
    }

    /** Whether text starts the way a mysqldump or mariadb-dump dump does. */
    public static function isDump(string $text): bool
    {
        return preg_match('/^-- (?:MySQL|MariaDB) dump\b/m', $text) === 1;
    }

    /** The first bytes of a file, empty when there is none. */
    private function headOf(string $path): string
    {
        $handle = $this->localFiles()->isFile($path) ? $this->localFiles()->openForReading($path) : null;
        if ($handle === null) {
            return '';
        }
        try {
            return (string)fread($handle, 4096);
        } finally {
            fclose($handle);
        }
    }

    /** mariadb-dump when it is there, since MariaDB 11 calls its mysqldump deprecated, mysqldump otherwise. */
    private function dumpTool(): string
    {
        $finder = new ExecutableFinder();
        foreach (['mariadb-dump', 'mysqldump'] as $tool) {
            if ($finder->find($tool, null, $this->getExtaDirs()) !== null) {
                return $tool;
            }
        }

        throw new \Exception('neither mariadb-dump nor mysqldump is installed');
    }

    /**
     * Run the dump tool and wait for it.
     *
     * @param list<string> $args
     *
     * @return array{exit: int|null, stdout: string, stderr: string}
     */
    private function runDumpTool(array $args, int $timeoutInSec): array
    {
        $process = $this->consoleService->findAndStartExecutableAsync($this->dumpTool(), $args, '', $this->getExtaDirs(), false, $timeoutInSec);
        if ($process === null) {
            throw new \Exception('the dump tool could not be started');
        }
        $process->wait();
        $out = $this->consoleService->getProcessOut($process)[0];

        return ['exit' => $process->getExitCode()] + $out;
    }

    /**
     * @return list<string>
     */
    private function getExtaDirs(): array
    {
        return '\\' === DIRECTORY_SEPARATOR ? ['c:\\xampp\\mysql\\bin\\'] : ['/usr/bin/', '/usr/local/bin/'];
    }

    /**
     * assert param is a not empty string.
     *
     * @phpstan-assert non-empty-string $param
     *
     * @throws \Exception
     */
    protected function assertParamIsNotEmptyString(string $name, mixed $param): void
    {
        if (empty($param)) {
            throw new \Exception("'$name' should not be empty in 'yeswiki.config.php'");
        }
        $this->assertParamIsString($name, $param);
    }

    /**
     * assert param is a string.
     *
     * @phpstan-assert string $param
     *
     * @throws \Exception
     */
    protected function assertParamIsString(string $name, mixed $param): void
    {
        if (!is_string($param)) {
            throw new \Exception("'$name' should be a string in 'yeswiki.config.php'");
        }
    }
}
