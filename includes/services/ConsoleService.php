<?php

namespace YesWiki\Core\Service;

use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use YesWiki\Wiki;

class ConsoleService
{
    protected const CONSOLE_BIN = 'includes/commands/console';

    protected $wiki;
    protected $params;
    protected $executableFinder;
    protected $phpBinaryFinder;

    public function __construct(Wiki $wiki, ParameterBagInterface $params)
    {
        $this->wiki = $wiki;
        $this->params = $params;
        $this->executableFinder = new ExecutableFinder();
        $this->phpBinaryFinder = new PhpExecutableFinder();
    }

    /**
     * @param string $command
     * @param array $args
     * @param string $subfolder
     * @param bool $newConsole
     * @param int $timeoutInSec
     * @return null|Process
     */
    public function startConsoleAsync(string $command, array $args = [], string $subfolder = "", bool $newConsole = true, int $timeoutInSec = 60): ?Process
    {
        $phpBinaryPath = $this->phpBinaryFinder->find();
        $newCommand = $phpBinaryPath;
        $newArgs = [self::CONSOLE_BIN,$command];
        foreach ($args as $arg) {
            $newArgs[] = $arg;
        }
        return $this->startRawCommandAsync($newCommand, $newArgs, $subfolder, $newConsole, $timeoutInSec);
    }

    public function getProcessOut(Process $process): array
    {
        $results = [];
        foreach ($process as $type => $data) {
            if ($process::OUT === $type) {
                $results[] = ['stdout' => $data];
            } else { // $process::ERR === $type
                $results[] = ['stderr' => $data];
            }
        }
        return $results;
    }

    /**
     * @param string $command
     * @param array $args
     * @param string $subfolder
     * @param int $timeoutInSec
     * @return null|array ['stdout|stderr' => $output]
     */
    public function startConsoleSync(string $command, array $args = [], string $subfolder = "", int $timeoutInSec = 60): ?array
    {
        $process = $this->startConsoleAsync($command, $args, $subfolder, false, $timeoutInSec);
        if (!$process) {
            return null;
        }
        $process->wait();
        return $this->getProcessOut($process);
    }

    /**
     * @param string $command
     * @param array $args
     * @param string $subfolder
     * @param bool $newConsole
     * @param int $timeoutInSec
     * @return null|Process
     */
    public function startRawCommandAsync(string $command, array $args = [], string $subfolder = "", bool $newConsole = true, int $timeoutInSec = 60): ?Process
    {
        if (empty($command)) {
            return null;
        }
        if (!empty($subfolder) && !is_dir(basename($subfolder))) {
            return null;
        }
        $folder = getcwd(). (empty($subfolder) ? '' : (DIRECTORY_SEPARATOR . basename($subfolder)));
        $params = [$command];
        foreach ($args as $arg) {
            $params[] = $arg;
        }
        $process = new Process($params, $folder);
        if ($timeoutInSec > 0) {
            $process->setTimeout($timeoutInSec); // default 60s
        }
        // this option allows a subprocess to continue running after the main script exited
        if ($newConsole) {
            $process->setOptions(['create_new_console' => true]);
        }
        $process->start();
        return $process;
    }
    
    /**
     * @param string $command
     * @param array $args
     * @param string $subfolder
     * @param int $timeoutInSec
     * @return null|array ['stdout|stderr' => $output]
     */
    public function startRawCommandSync(string $command, array $args = [], string $subfolder = "", int $timeoutInSec = 60): ?array
    {
        $process = $this->startRawCommandAsync($command, $args, $subfolder, false, $timeoutInSec);
        if (!$process) {
            return null;
        }
        $process->wait();
        return $this->getProcessOut($process);
    }

    /**
     * @param string $executableName
     * @param array $args
     * @param string $subfolder
     * @param array $extraDirsWhereSearch
     * @param bool $newConsole
     * @param int $timeoutInSec
     * @return null|Process
     */
    public function findAndStartExecutableAsync(string $executableName, array $args = [], string $subfolder = "", array $extraDirsWhereSearch=[], bool $newConsole = true, int $timeoutInSec = 60): ?Process
    {
        $executable = $this->findExecutable($executableName, $extraDirsWhereSearch);
        if (empty($executable)) {
            throw new Exception("Executable \"$executableName\" not found !");
        }
        return $this->startRawCommandAsync($executable, $args, $subfolder, $newConsole, $timeoutInSec);
    }

    /**
     * @param string $executableName
     * @param array $args
     * @param string $subfolder
     * @param array $extraDirsWhereSearch
     * @param int $timeoutInSec
     * @return null|array ['command'=>['stdout|stderr' => $output]]
     */
    public function findAndStartExecutableSync(string $executableName, array $args = [], string $subfolder = "", array $extraDirsWhereSearch=[], int $timeoutInSec = 60): ?array
    {
        $process = $this->findAndStartExecutableAsync($executableName, $args, $subfolder, $extraDirsWhereSearch, false, $timeoutInSec);
        if (!$process) {
            return null;
        }
        $process->wait();
        return $this->getProcessOut($process);
    }

    /**
     * format html for CLI
     * @param string $input
     * @return string $output
     */
    public function formatHtmlForCLI(string $input): string
    {
        $bufferedOutput = strip_tags($input, '<br><hr><em><strong>');
        $output = preg_replace(
            ['#<[bh]r ?/?>#Ui', '/<(em|strong)>/Ui', '#</ ?(em|strong)>#Ui'],
            ["\n", "\e[1m", "\e[0m"],
            $bufferedOutput
        );
        return $output;
    }

    /**
     * @param string $name
     * @param array $extraDirs wherer search
     * @return string
     * @throws Exception
     */
    protected function findExecutable(string $name, array $extraDirs = []): string
    {
        if (empty($name)) {
            throw new Exception("'name' should not be empty !");
        }
        return $this->executableFinder->find($name, "", $extraDirs);
    }
}
