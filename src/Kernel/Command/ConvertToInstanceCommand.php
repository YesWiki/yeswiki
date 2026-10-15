<?php

namespace YesWiki\Kernel\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use YesWiki\Kernel\Service\InstanceEntryPoints;
use YesWiki\Kernel\Service\ProgramVersion;

/** Turn a wiki installed with its own full copy of YesWiki into a farm Instance of this Program tree. */
class ConvertToInstanceCommand extends Command implements RunsOutsideAnInstance
{
    private const KEPT = ['yeswiki.config.php', '.htaccess', ...InstanceEntryPoints::DATA_FOLDERS];

    private const OWN_FOLDERS = ['extensions' => 'custom/extensions', 'themes' => 'custom/themes'];

    private const BUILD_AND_TESTS = [
        'Makefile', 'binary', 'docker', 'eslint.config.mjs', 'node_modules', 'phpstan', 'playwright-report',
        'playwright.config.ts', 'shell.nix', 'test-results', 'test.config.php', 'tests', 'tools',
    ];

    protected ?ContainerInterface $services;

    public function __construct(?ContainerInterface $services = null)
    {
        parent::__construct();
        $this->services = $services;
    }

    protected function configure()
    {
        $this
            ->setName('core:convert-to-instance')
            ->setDescription('Turn a wiki installed with its own copy of YesWiki into an instance of a shared program.')
            ->setHelp("From the wiki to convert:  <info>./yeswicli core:convert-to-instance /path/to/shared/yeswiki</info>\n" .
                "From the shared program:   <info>./yeswicli core:convert-to-instance --wiki=/path/to/wiki</info>\n\n" .
                "In the wiki, it:\n" .
                "- keeps yeswiki.config.php, .htaccess, files/, custom/, private/ and any other file of its own\n" .
                "- moves the extensions and themes the program does not ship into custom/extensions/ and custom/themes/\n" .
                "- moves into a backup folder beside the wiki: every file the program also has, the dotfiles\n" .
                "  (.git, .github, .env...) and the build and test files (node_modules, tests, docker, Makefile...)\n" .
                "- empties cache/ and writes index.php and yeswicli pointing at the program\n\n" .
                "It shows what it will do and asks before doing it. Then run <wiki>/yeswicli migrate,\n" .
                "check the wiki, and delete the backup folder.\n")
            ->addArgument('program', InputArgument::OPTIONAL, 'The shared YesWiki program, when run from the wiki to convert')
            ->addOption('wiki', null, InputOption::VALUE_REQUIRED, 'The wiki to convert, when run from the shared program')
            ->addOption('backup', null, InputOption::VALUE_REQUIRED, 'Where to move the replaced files (default: <wiki>.full-install-<date>)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print what would happen and change nothing')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Do not ask for confirmation')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $wiki = $input->getOption('wiki');
        $program = $input->getArgument('program');
        if (is_string($wiki) && $wiki !== '') {
            if (is_string($program) && $program !== '') {
                $output->writeln('<error>Give either the program (from the wiki) or --wiki (from the program), not both.</error>');

                return Command::INVALID;
            }

            return $this->convert($this->absolute(rtrim($wiki, '/')), $input, $output);
        }
        if (!is_string($program) || $program === '') {
            $output->writeln('<error>Run it from the wiki to convert, with the shared program\'s path:</error>');
            $output->writeln('  ./yeswicli core:convert-to-instance /path/to/shared/yeswiki');

            return Command::INVALID;
        }

        return $this->delegate($this->absolute(rtrim($program, '/')), $input, $output);
    }

    /** From the wiki: hand the conversion to the program's own console, since this wiki's copy of the code is what gets moved. */
    private function delegate(string $program, InputInterface $input, OutputInterface $output): int
    {
        $programDir = realpath($program);
        if ($programDir === false || !is_file($programDir . '/src/commands/console') || !is_file($programDir . '/composer.json')) {
            $output->writeln("<error>$program is not a YesWiki program folder.</error>");

            return Command::FAILURE;
        }
        $wikiDir = (string)realpath(YESWIKI_INSTANCE_DIR);
        if ($programDir === $wikiDir) {
            $output->writeln("<error>$program is this wiki itself; give the path of the shared program.</error>");

            return Command::FAILURE;
        }

        $command = [PHP_BINARY, $programDir . '/src/commands/console', 'core:convert-to-instance', '--wiki=' . $wikiDir];
        $backup = $input->getOption('backup');
        if (is_string($backup) && $backup !== '') {
            $command[] = '--backup=' . $this->absolute(rtrim($backup, '/'));
        }
        foreach (['dry-run', 'yes'] as $flag) {
            if ($input->getOption($flag)) {
                $command[] = '--' . $flag;
            }
        }
        if (!$input->isInteractive() || !stream_isatty(STDIN)) {
            $command[] = '--no-interaction';
        }

        $workDir = sys_get_temp_dir() . '/yeswiki-convert-' . getmypid();
        @mkdir($workDir, 0700, true);
        $environment = array_diff_key(getenv(), array_flip(['YESWIKI_INSTANCE_DIR', 'YESWIKI_PROGRAM_DIR', 'YESWIKI_CONFIG_FILE']));
        $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $workDir, $environment);
        $status = is_resource($process) ? proc_close($process) : Command::FAILURE;
        $this->empty($workDir);
        @rmdir($workDir);

        exit($status);
    }

    private function convert(string $path, InputInterface $input, OutputInterface $output): int
    {
        $refusal = $this->refusal($path);
        if ($refusal !== null) {
            $output->writeln("<error>$refusal</error>");

            return Command::FAILURE;
        }
        $path = (string)realpath($path);

        $backup = $input->getOption('backup');
        $backup = is_string($backup) && $backup !== ''
            ? $this->absolute(rtrim($backup, '/'))
            : $path . '.full-install-' . date('Ymd-His');
        if (file_exists($backup)) {
            $output->writeln("<error>$backup already exists, choose another --backup folder.</error>");

            return Command::FAILURE;
        }
        if (!is_dir(\dirname($backup)) || !is_writable(\dirname($backup))) {
            $output->writeln('<error>Cannot create the backup folder in ' . \dirname($backup) . ': not writable. Choose one with --backup=.</error>');

            return Command::FAILURE;
        }

        $output->writeln("Wiki to convert: <info>$path</info>" . $this->baseUrlOf($path));
        $output->writeln('Shared program:  <info>' . YESWIKI_PROGRAM_DIR . '</info>');
        $output->writeln("Backup folder:   <info>$backup</info>");
        $output->writeln('');

        $plan = $this->plan($path, $backup);
        foreach ($plan as [$from, $to]) {
            $output->writeln("will move $from -> $to");
        }
        foreach ($this->unknownEntries($path) as $entry) {
            $output->writeln("will keep $path/$entry (not part of YesWiki)");
        }
        $output->writeln("will empty $path/cache/");
        $output->writeln("will write $path/index.php and $path/yeswicli -> " . YESWIKI_PROGRAM_DIR);
        if ($input->getOption('dry-run')) {
            $output->writeln('Dry run: nothing changed.');

            return Command::SUCCESS;
        }

        if (!$input->getOption('yes')) {
            if (!$input->isInteractive() || !stream_isatty(STDIN)) {
                $output->writeln('<error>Nothing changed: add --yes to convert without a question.</error>');

                return Command::FAILURE;
            }
            $question = new ConfirmationQuestion("\nConvert $path into an instance of " . YESWIKI_PROGRAM_DIR . '? [y/N] ', false);
            if (!(new QuestionHelper())->ask($input, $output, $question)) {
                $output->writeln('Nothing changed.');

                return Command::FAILURE;
            }
        }

        if (!@mkdir($backup, 0755, true)) {
            $output->writeln("<error>Could not create $backup; nothing changed.</error>");

            return Command::FAILURE;
        }
        foreach ($plan as [$from, $to]) {
            if (!is_dir(\dirname($to))) {
                @mkdir(\dirname($to), 0755, true);
            }
            if (!@rename($from, $to)) {
                $output->writeln("<error>Could not move $from to $to; stopped there, the files already moved are in $backup.</error>");

                return Command::FAILURE;
            }
        }
        $this->empty($path . '/cache');

        $error = InstanceEntryPoints::write($path, YESWIKI_PROGRAM_DIR);
        if ($error !== null) {
            $output->writeln("<error>$error; the replaced files are in $backup.</error>");

            return Command::FAILURE;
        }

        $output->writeln('Moved ' . count($plan) . " entries to $backup.");
        $output->writeln("<info>$path is now an instance of " . YESWIKI_PROGRAM_DIR . '</info>');
        $output->writeln('Next steps:');
        $output->writeln(" - run <info>$path/yeswicli migrate</info>");
        $output->writeln(' - nginx: the vhost must send every request to index.php (`try_files $uri /index.php$is_args$args;`)');
        $output->writeln(" - if you ran this as root: <info>$path/yeswicli core:fix-ownership</info>");
        $output->writeln(" - check the wiki in a browser, then delete $backup");

        return Command::SUCCESS;
    }

    private function baseUrlOf(string $path): string
    {
        $config = (string)@file_get_contents($path . '/yeswiki.config.php');

        return preg_match('/[\'"]base_url[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $config, $m) === 1 ? " ({$m[1]})" : '';
    }

    private function absolute(string $path): string
    {
        return preg_match('~^(/|[A-Za-z]:[\\\\/])~', $path) ? $path : YESWIKI_INSTANCE_DIR . '/' . $path;
    }

    private function refusal(string $path): ?string
    {
        if ($path === '' || !is_dir($path)) {
            return "$path is not a folder.";
        }
        if (realpath($path) === realpath(YESWIKI_PROGRAM_DIR)) {
            return 'That is this YesWiki program itself; give the path of the wiki to convert.';
        }
        if (!is_file($path . '/yeswiki.config.php')) {
            return "No yeswiki.config.php in $path: not an installed wiki.";
        }
        if (InstanceEntryPoints::isInstance($path)) {
            return "$path is already an instance.";
        }
        if (!is_file($path . '/composer.json') || !is_dir($path . '/src')) {
            return "$path does not hold a copy of YesWiki to replace.";
        }

        $theirs = new ProgramVersion((string)file_get_contents($path . '/composer.json'));
        $ours = new ProgramVersion();
        if ($theirs->releaseLine() !== $ours->releaseLine()) {
            return sprintf(
                '%s runs the %s release line and this program is %s; upgrade the wiki first.',
                $path,
                $theirs->releaseLine() ?: 'doryphore (or older)',
                $ours->releaseLine()
            );
        }
        if ($theirs->version() !== ProgramVersion::DEV && $ours->version() !== ProgramVersion::DEV
            && version_compare($theirs->version(), $ours->version(), '>')) {
            return "$path runs YesWiki {$theirs->version()}, newer than this program ({$ours->version()}); update the program first.";
        }

        return null;
    }

    /** @return list<array{0: string, 1: string}> the moves, from => to */
    private function plan(string $path, string $backup): array
    {
        $moves = [];
        foreach (self::OWN_FOLDERS as $folder => $customFolder) {
            foreach ($this->entries($path . '/' . $folder) as $name) {
                if (!is_dir($path . '/' . $folder . '/' . $name) || file_exists(YESWIKI_PROGRAM_DIR . '/' . $folder . '/' . $name)) {
                    continue;
                }
                $target = $path . '/' . $customFolder . '/' . $name;
                if (!file_exists($target)) {
                    $moves[] = [$path . '/' . $folder . '/' . $name, $target];
                }
            }
        }
        foreach ($this->entries($path) as $entry) {
            if ($this->replaced($entry)) {
                $moves[] = [$path . '/' . $entry, $backup . '/' . $entry];
            }
        }

        return $moves;
    }

    /** @return list<string> */
    private function unknownEntries(string $path): array
    {
        return array_values(array_filter(
            $this->entries($path),
            fn (string $entry): bool => !in_array($entry, self::KEPT, true) && !$this->replaced($entry)
        ));
    }

    /** Whether a top-level entry of the wiki goes to the backup: what the program ships, dotfiles, and build or test tooling. */
    private function replaced(string $entry): bool
    {
        if (in_array($entry, self::KEPT, true)) {
            return false;
        }

        return str_starts_with($entry, '.')
            || in_array($entry, self::BUILD_AND_TESTS, true)
            || file_exists(YESWIKI_PROGRAM_DIR . '/' . $entry);
    }

    /** @return list<string> */
    private function entries(string $folder): array
    {
        $entries = is_dir($folder) ? scandir($folder) : false;

        return $entries === false ? [] : array_values(array_diff($entries, ['.', '..']));
    }

    private function empty(string $folder): void
    {
        foreach ($this->entries($folder) as $entry) {
            $item = $folder . '/' . $entry;
            if (is_dir($item) && !is_link($item)) {
                $this->empty($item);
                @rmdir($item);
            } else {
                @unlink($item);
            }
        }
    }
}
