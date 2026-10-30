<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use YesWiki\Files\Service\LocalFiles;

class ConsoleService
{
    protected const CONSOLE_BIN = 'src/commands/console';

    /** The console, which lives in the Program and not in the Instance. */
    public static function console(): string
    {
        if (\defined('YESWIKI_PROGRAM_DIR')) {
            return YESWIKI_PROGRAM_DIR . DIRECTORY_SEPARATOR . self::CONSOLE_BIN;
        }

        return self::CONSOLE_BIN;
    }

    protected ParameterBagInterface $params;
    protected ExecutableFinder $executableFinder;
    protected PhpExecutableFinder $phpBinaryFinder;

    public function __construct(ParameterBagInterface $params, private readonly LocalFiles $localFiles = new LocalFiles())
    {
        $this->params = $params;
        $this->executableFinder = new ExecutableFinder();
        $this->phpBinaryFinder = new PhpExecutableFinder();
    }

    /**
     * @param list<string|int> $args
     */
    public function startConsoleAsync(string $command, array $args = [], string $subfolder = '', bool $newConsole = true, int $timeoutInSec = 60): ?Process
    {
        $phpBinaryPath = $this->phpBinary();
        if ($phpBinaryPath === null) {
            return null;
        }
        $newCommand = $phpBinaryPath;
        $newArgs = [self::console(), $command];
        foreach ($args as $arg) {
            $newArgs[] = $arg;
        }

        return $this->startRawCommandAsync($newCommand, $newArgs, $subfolder, $newConsole, $timeoutInSec);
    }

    /**
     * Starts a console command that outlives the web request starting it, as the queue's processes do (startDetached()); null when no PHP binary is found.
     *
     * @param list<string> $args
     */
    public function startDetachedConsole(string $command, array $args = []): ?Process
    {
        $phpBinaryPath = $this->phpBinary();
        if ($phpBinaryPath === null) {
            return null;
        }

        return $this->startDetached($phpBinaryPath, [self::console(), $command, ...$args]);
    }

    /**
     * Starts a process nobody waits for, in the wiki's folder.
     *
     * - Its output goes nowhere rather than to pipes: when the request ends, its Process object is
     *   dropped and closes them, and the process died of SIGPIPE at the first line it printed.
     * - The wiki's folder rather than getcwd(): the console finds its wiki in the folder it runs in,
     *   and mod_php may run the end of a request elsewhere.
     * - The console's ownership check is lifted (CliOwnership::ANY_USER): the web server's user
     *   starts it, the one that writes the wiki's files from every request anyway.
     * - No timeout: nobody is left to enforce it.
     *
     * @param list<string> $args
     */
    public function startDetached(string $command, array $args = []): Process
    {
        $process = new Process([$command, ...$args], \defined('YESWIKI_INSTANCE_DIR') ? YESWIKI_INSTANCE_DIR : null, [CliOwnership::ANY_USER => '1'], null, null);
        $process->disableOutput();
        $process->setOptions(['create_new_console' => true]);
        $process->start();

        return $process;
    }

    /**
     * @return array{array{stdout: string, stderr: string}}
     */
    public function getProcessOut(Process $process): array
    {
        $stdout = '';
        $stderr = '';
        foreach ($process as $type => $data) {
            if ($process::OUT === $type) {
                $stdout .= $data;
            } else {
                $stderr .= $data;
            }
        }

        return [['stdout' => $stdout, 'stderr' => $stderr]];
    }

    /**
     * @param list<string|int> $args
     *
     * @return array{array{stdout: string, stderr: string}}|null
     */
    public function startConsoleSync(string $command, array $args = [], string $subfolder = '', int $timeoutInSec = 60): ?array
    {
        $process = $this->startConsoleAsync($command, $args, $subfolder, false, $timeoutInSec);
        if (!$process) {
            return null;
        }
        $process->wait();

        return $this->getProcessOut($process);
    }

    /**
     * @param list<string|int> $args
     */
    public function startRawCommandAsync(string $command, array $args = [], string $subfolder = '', bool $newConsole = true, int $timeoutInSec = 60): ?Process
    {
        if (empty($command)) {
            return null;
        }
        if (!empty($subfolder) && !$this->localFiles->isDirectory(basename($subfolder))) {
            return null;
        }
        $folder = getcwd() . (empty($subfolder) ? '' : (DIRECTORY_SEPARATOR . basename($subfolder)));
        $params = [$command];
        foreach ($args as $arg) {
            $params[] = $arg;
        }
        $process = new Process($params, $folder);
        if ($timeoutInSec > 0) {
            $process->setTimeout($timeoutInSec);
        }

        if ($newConsole) {
            $process->setOptions(['create_new_console' => true]);
        }
        $process->start();

        return $process;
    }

    /**
     * @param list<string|int> $args
     *
     * @return array{array{stdout: string, stderr: string}}|null
     */
    public function startRawCommandSync(string $command, array $args = [], string $subfolder = '', int $timeoutInSec = 60): ?array
    {
        $process = $this->startRawCommandAsync($command, $args, $subfolder, false, $timeoutInSec);
        if (!$process) {
            return null;
        }
        $process->wait();

        return $this->getProcessOut($process);
    }

    /**
     * @param list<string|int> $args
     * @param list<string>     $extraDirsWhereSearch
     */
    public function findAndStartExecutableAsync(string $executableName, array $args = [], string $subfolder = '', array $extraDirsWhereSearch = [], bool $newConsole = true, int $timeoutInSec = 60): ?Process
    {
        $executable = $this->findExecutable($executableName, $extraDirsWhereSearch);
        if (empty($executable)) {
            throw new \Exception("Executable \"$executableName\" not found !");
        }

        return $this->startRawCommandAsync($executable, $args, $subfolder, $newConsole, $timeoutInSec);
    }

    /**
     * @param list<string|int> $args
     * @param list<string>     $extraDirsWhereSearch
     *
     * @return array{array{stdout: string, stderr: string}}|null
     */
    public function findAndStartExecutableSync(string $executableName, array $args = [], string $subfolder = '', array $extraDirsWhereSearch = [], int $timeoutInSec = 60): ?array
    {
        $process = $this->findAndStartExecutableAsync($executableName, $args, $subfolder, $extraDirsWhereSearch, false, $timeoutInSec);
        if (!$process) {
            return null;
        }
        $process->wait();

        return $this->getProcessOut($process);
    }

    /** The PHP to run the console with: ASYNC_PHP_BINARY, or the one Symfony finds; null when there is none. */
    private function phpBinary(): ?string
    {
        $phpBinaryPath = getenv('ASYNC_PHP_BINARY');
        if (!$phpBinaryPath) {
            $phpBinaryPath = $this->phpBinaryFinder->find();
        }

        return empty($phpBinaryPath) ? null : $phpBinaryPath;
    }

    /**
     * format html for CLI.
     *
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

        return (string)$output;
    }

    /**
     * @param list<string> $extraDirs where to search
     *
     * @throws \Exception
     */
    protected function findExecutable(string $name, array $extraDirs = []): ?string
    {
        if (empty($name)) {
            throw new \Exception("'name' should not be empty !");
        }

        return $this->executableFinder->find($name, '', $extraDirs);
    }
}
