<?php

namespace YesWiki\Kernel\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\ConsoleService;
use YesWiki\Kernel\Service\ThrowableFormatter;

class DbCommand extends Command
{
    protected $consoleService;
    protected $params;
    protected ContainerInterface $services;

    public function __construct(ContainerInterface $services)
    {
        parent::__construct();
        $this->consoleService = $services->get(ConsoleService::class);
        $this->params = $services->get(ParameterBagInterface::class);
        $this->services = $services;
    }

    protected function configure()
    {
        $this
            ->setName('core:exportdb')
            // the short description shown while running "./yeswicli list"
            ->setDescription('Manage database of the YesWiki.')

            // the full command description shown when running the command with
            // the "--help" option
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
     * get params to connect to dB.
     *
     * @return array [
     *               'hostArg' => array,
     *               'databasename' => string
     *               'tablePrefix' => string
     *               'username' => string
     *               'password' => string
     *               ]
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
        $realFilePath = realpath(dirname($filepath)) . DIRECTORY_SEPARATOR . basename($filepath);
        extract($this->getDbParams());
        try {
            $results = $this->consoleService->findAndStartExecutableSync(
                'mysqldump',
                array_merge(
                    $hostArg,
                    [
                        "--user=$username",
                        "--password=$password",
                        "--result-file=$realFilePath",
                        $databasename, // databasename
                        "{$tablePrefix}pages", // tables
                        "{$tablePrefix}triples", // tables
                        "{$tablePrefix}links", // tables
                        "{$tablePrefix}referrers", // tables
                    ]
                ), // args
                '', // subfolder
                $this->getExtaDirs(), // extraDirsWhereSearch
                120 // timeoutInSec (2 minutes)
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

    /**
     * test connection to mysqldump.
     *
     * @return int Command:code
     *
     * @throws \Throwable
     */
    private function test(OutputInterface $output): int
    {
        extract($this->getDbParams());
        try {
            $results = $this->consoleService->findAndStartExecutableSync(
                'mysqldump',
                [
                    '-V', // output version
                ], // args
                '', // subfolder
                $this->getExtaDirs(), // extraDirsWhereSearch
                10 // timeoutInSec
            );
            $outputResult = $this->getOutput($results);
            if (preg_match("/^mysqldump(?:\.exe)?\s*Ver\s*\d+\.?\d*.*/i", $outputResult)) {
                // test connecting to database

                $results = $this->consoleService->findAndStartExecutableSync(
                    'mysqldump',
                    array_merge(
                        $hostArg,
                        [
                            "--user=$username",
                            "--password=$password",
                            '-t', // no table info
                            '-d', // no table data
                            $databasename, // databasename
                        ]
                    ), // args
                    '', // subfolder
                    $this->getExtaDirs(), // extraDirsWhereSearch
                    10 // timeoutInSec
                );
                $outputResult = $this->getOutput($results);
                if (empty($outputResult)) {
                    throw new \Exception('output should not be empty during test to connect to database via mysql');
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

    /**
     * assert param is a not empty string.
     *
     * @throws \Exception
     */
    protected function assertParamIsNotEmptyString(string $name, $param)
    {
        if (empty($param)) {
            throw new \Exception("'$name' should not be empty in 'yeswiki.config.php'");
        }
        $this->assertParamIsString($name, $param);
    }

    /**
     * assert param is a string.
     *
     * @throws \Exception
     */
    protected function assertParamIsString(string $name, $param)
    {
        if (!is_string($param)) {
            throw new \Exception("'$name' should be a string in 'yeswiki.config.php'");
        }
    }
}
