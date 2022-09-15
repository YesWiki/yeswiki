<?php

namespace YesWiki\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use YesWiki\Wiki;

class ConsoleService
{
    protected const CONSOLE_BIN = 'includes/commands/console';

    protected $wiki;
    protected $params;
    protected $phpBinaryFinder;

    public function __construct(Wiki $wiki, ParameterBagInterface $params)
    {
        $this->wiki = $wiki;
        $this->params = $params;
        $this->phpBinaryFinder = new PhpExecutableFinder();
    }

    /**
     * @param string $command
     * @param array $args
     * @param string $subfolder
     * @param bool $newConsole;
     * @return array ['command'=>['stdout|stderr' => $output]]
     */
    public function startConsoleAsync(string $command, array $args = [], string $subfolder = "", bool $newConsole = true): ?Process
    {
        if (empty($command)) {
            return null;
        }
        if (!empty($subfolder) && !is_dir(basename($subfolder))) {
            return null;
        }
        $folder = getcwd(). (empty($subfolder) ? '' : (DIRECTORY_SEPARATOR . basename($subfolder)));
        $phpBinaryPath = $this->phpBinaryFinder->find();
        $params = [$phpBinaryPath,self::CONSOLE_BIN,$command];
        foreach ($args as $arg) {
            $params[] = $arg;
        }
        $process = new Process($params, $folder);
        // $process->setTimeout(60); // default 60s
        // this option allows a subprocess to continue running after the main script exited
        if ($newConsole) {
            $process->setOptions(['create_new_console' => true]);
        }
        $process->start();
        return $process;
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
     * @return array ['stdout|stderr' => $output]
     */
    public function startConsoleSync(string $command, array $args = [], string $subfolder = ""): array
    {
        $process = $this->startConsoleAsync($command, $args, $subfolder, false);
        if (!$process) {
            return null;
        }
        $process->wait();
        return $this->getProcessOut($process);
    }
}
