<?php

namespace YesWiki\Core\Commands;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Throwable;
use YesWiki\Core\Service\ConsoleService;
use YesWiki\Wiki;

class DbCommand extends Command
{
    // the name of the command (the part after "php includes/commands/console")
    protected static $defaultName = 'core:exportdb';

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
            // the short description shown while running "php includes/commands/console list"
            ->setDescription('Manage database of the YesWiki.')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp("Manage database of the YesWiki.\n".
                "To test use '--test'\n")

            ->addOption('test', 't', InputOption::VALUE_NONE, 'Test the connection to mysqldump (return OK/NOK)')
            ->addOption('filepath', 'f', InputOption::VALUE_REQUIRED, '.sql file path where export db')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isTest = $input->getOption('test');
        $filepath = $input->getOption('filepath');

        if (!$isTest && (empty($filepath) || substr($filepath, -4) != ".sql")) {
            $output->writeln("Invalid options : option '--filepath' is required and should end by '.sql' if not testing.");
            return Command::INVALID;
        }

        if ($isTest) {
            return $this->test($output);
        }
        return $this->export($output, $filepath);
    }

    /**
     * export db via mysqldump
     * @param OutputInterface $output
     * @param string $filepath
     * @return int Command:code
     * @throws Exception
     * @throws Throwable
     */
    private function export(OutputInterface $output, string $filepath): int
    {
        $realFilePath = realpath(dirname($filepath)).DIRECTORY_SEPARATOR.basename($filepath);
        $hostname = $this->params->get('mysql_host');
        $this->assertParamIsNotEmptyString('mysql_host', $hostname);

        $databasename = $this->params->get('mysql_database');
        $this->assertParamIsNotEmptyString('mysql_database', $databasename);

        $tablePrefix = $this->params->get('table_prefix');
        $this->assertParamIsNotEmptyString('table_prefix', $tablePrefix);

        $username = $this->params->get('mysql_user');
        $this->assertParamIsString('mysql_user', $username);

        $password = $this->params->get('mysql_password');
        $this->assertParamIsString('mysql_password', $password);
        try {
            $results = $this->consoleService->findAndStartExecutableSync(
                "mysqldump",
                [
                    "--host=$hostname",
                    "--user=$username",
                    "--password=$password",
                    "--result-file=$realFilePath",
                    $databasename, // databasename
                    "{$tablePrefix}users", // tables
                    "{$tablePrefix}pages", // tables
                    "{$tablePrefix}nature", // tables
                    "{$tablePrefix}triples", // tables
                    "{$tablePrefix}acls", // tables
                    "{$tablePrefix}links", // tables
                    "{$tablePrefix}referrers", // tables
                ], // args
                "", // subfolder
                $this->getExtaDirs(), // extraDirsWhereSearch
                120 // timeoutInSec (2 minutes)
            );
            $err = $this->getErr($results);
            if (empty($err)) {
                return Command::SUCCESS;
            } else {
                $output->writeln($err);
            }
        } catch (Throwable $ex) {
            $output->writeln("System error when testing mysqldump : {$ex->getMessage()}");
        }
        return Command::FAILURE;
    }

    /**
     * test connection to mysqldump
     * @param OutputInterface $output
     * @return int Command:code
     * @throws Throwable
     */
    private function test(OutputInterface $output): int
    {
        try {
            $results = $this->consoleService->findAndStartExecutableSync(
                "mysqldump",
                [
                    "-V", // output version
                ], // args
                "", // subfolder
                $this->getExtaDirs(), // extraDirsWhereSearch
                10 // timeoutInSec
            );
            $outputResult = $this->getOutput($results);
            if (preg_match("/^mysqldump(?:\.exe)?\s*Ver\s*\d+\.?\d*.*/i", $outputResult)) {
                $output->writeln("OK");
                return Command::SUCCESS;
            }
        } catch (Throwable $ex) {
            $output->writeln("System error when testing mysqldump : {$ex->getMessage()} in {$ex->getFile()}, line {$ex->getLine()}");
        }
        $output->writeln("NOK");
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
        return '\\' === DIRECTORY_SEPARATOR ? ["c:\\xampp\\mysql\\bin\\"] : ["/usr/bin/","/usr/local/bin/"];
    }

    /**
    * assert param is a not empty string
    * @param string $name
    * @param mixed $param
    * @throws Exception
    */
   protected function assertParamIsNotEmptyString(string $name, $param)
   {
       if (empty($param)) {
           throw new Exception("'$name' should not be empty in 'wakka.config.php'");
       }
       $this->assertParamIsString($name, $param);
   }

    /**
     * assert param is a string
     * @param string $name
     * @param mixed $param
     * @throws Exception
     */
    protected function assertParamIsString(string $name, $param)
    {
        if (!is_string($param)) {
            throw new Exception("'$name' should be a string in 'wakka.config.php'");
        }
    }
}
