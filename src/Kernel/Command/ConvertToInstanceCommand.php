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

/** Turn this wiki, installed with its own full copy of YesWiki, into an Instance of a shared Program tree. */
class ConvertToInstanceCommand extends Command implements RunsOutsideAnInstance
{
    private const KEPT = ['yeswiki.config.php', '.htaccess', ...InstanceEntryPoints::DATA_FOLDERS];

    private const OWN_FOLDERS = ['extensions' => 'custom/extensions', 'themes' => 'custom/themes'];

    private const BUILD_AND_TESTS = [
        'Makefile', 'binary', 'docker', 'eslint.config.mjs', 'node_modules', 'phpstan', 'playwright-report',
        'playwright.config.ts', 'shell.nix', 'test-results', 'test.config.php', 'tests', 'tools',
    ];

    private const PROGRAM_FILES = ['index.php', 'composer.json', 'src/commands/console', 'vendor/autoload.php'];

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
            ->setDescription('Turn this wiki, installed with its own copy of YesWiki, into an instance of a shared program.')
            ->setHelp("Run it in the wiki, as the wiki's own user:\n" .
                "  <info>./yeswicli core:convert-to-instance /path/to/shared/yeswiki</info>\n\n" .
                "It checks that the shared program is readable and on the same release line, then:\n" .
                "- keeps yeswiki.config.php, .htaccess, files/, custom/, private/ and any other file of its own\n" .
                "- moves the extensions and themes the program does not ship into custom/extensions/ and custom/themes/\n" .
                "- moves into private/full-install-<date>/: every file the program also has, the dotfiles\n" .
                "  (.git, .github, .env...) and the build and test files (node_modules, tests, docker, Makefile...)\n" .
                "- empties cache/ and writes index.php and yeswicli pointing at the program\n\n" .
                "It shows what it will do and asks before doing it. Then run ./yeswicli migrate,\n" .
                "check the wiki, and delete the backup folder.\n")
            ->addArgument('program', InputArgument::REQUIRED, 'The shared YesWiki program, relative or absolute path')
            ->addOption('backup', null, InputOption::VALUE_REQUIRED, 'Where to move the replaced files (default: private/full-install-<date>)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print what would happen and change nothing')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Do not ask for confirmation')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $wiki = (string)realpath(YESWIKI_INSTANCE_DIR);
        $program = $this->absolute(rtrim((string)$input->getArgument('program'), '/'));

        $refusal = $this->refusal($wiki, $program);
        if ($refusal !== null) {
            $output->writeln("<error>$refusal</error>");

            return Command::FAILURE;
        }
        $program = (string)realpath($program);

        $backup = $input->getOption('backup');
        $backup = is_string($backup) && $backup !== ''
            ? $this->absolute(rtrim($backup, '/'))
            : $wiki . '/private/full-install-' . date('Ymd-His');
        if (file_exists($backup)) {
            $output->writeln("<error>$backup already exists, choose another --backup folder.</error>");

            return Command::FAILURE;
        }
        $backupParent = \dirname($backup);
        while (!is_dir($backupParent) && $backupParent !== \dirname($backupParent)) {
            $backupParent = \dirname($backupParent);
        }
        if (!is_writable($backupParent)) {
            $output->writeln("<error>Cannot create the backup folder in $backupParent: not writable. Choose one with --backup=.</error>");

            return Command::FAILURE;
        }

        $output->writeln("Wiki to convert: <info>$wiki</info>" . $this->baseUrlOf($wiki));
        $output->writeln("Shared program:  <info>$program</info>");
        $output->writeln("Backup folder:   <info>$backup</info>");
        $output->writeln('');

        $plan = $this->plan($wiki, $program, $backup);
        foreach ($plan as [$from, $to]) {
            $output->writeln("will move $from -> $to");
        }
        foreach ($this->unknownEntries($wiki, $program) as $entry) {
            $output->writeln("will keep $wiki/$entry (not part of YesWiki)");
        }
        $output->writeln("will empty $wiki/cache/");
        $output->writeln("will write $wiki/index.php and $wiki/yeswicli -> $program");
        if ($input->getOption('dry-run')) {
            $output->writeln('Dry run: nothing changed.');

            return Command::SUCCESS;
        }

        if (!$input->getOption('yes')) {
            if (!$input->isInteractive() || !stream_isatty(STDIN)) {
                $output->writeln('<error>Nothing changed: add --yes to convert without a question.</error>');

                return Command::FAILURE;
            }
            $question = new ConfirmationQuestion("\nConvert $wiki into an instance of $program? [y/N] ", false);
            if (!(new QuestionHelper())->ask($input, $output, $question)) {
                $output->writeln('Nothing changed.');

                return Command::FAILURE;
            }
        }

        if (!@mkdir($backup, 0750, true)) {
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
        $this->empty($wiki . '/cache');

        $error = InstanceEntryPoints::write($wiki, $program);
        if ($error !== null) {
            $output->writeln("<error>$error; the replaced files are in $backup.</error>");

            return Command::FAILURE;
        }

        $output->writeln('Moved ' . count($plan) . " entries to $backup.");
        $output->writeln("<info>$wiki is now an instance of $program</info>");
        $output->writeln('Next steps:');
        $output->writeln(' - run <info>./yeswicli migrate</info>');
        $output->writeln(" - the web server's user must be able to read $program");
        $output->writeln(' - nginx: the vhost must send every request to index.php (`try_files $uri /index.php$is_args$args;`)');
        $output->writeln(" - check the wiki in a browser, then delete $backup");

        return Command::SUCCESS;
    }

    private function absolute(string $path): string
    {
        return preg_match('~^(/|[A-Za-z]:[\\\\/])~', $path) ? $path : YESWIKI_INSTANCE_DIR . '/' . $path;
    }

    private function refusal(string $wiki, string $program): ?string
    {
        if (!is_file($wiki . '/yeswiki.config.php')) {
            return "No yeswiki.config.php in $wiki: run this inside the installed wiki.";
        }
        if (InstanceEntryPoints::isInstance($wiki)) {
            return "$wiki is already an instance.";
        }
        if (!is_file($wiki . '/composer.json') || !is_dir($wiki . '/src')) {
            return "$wiki does not hold a copy of YesWiki to replace.";
        }
        if (!is_dir($program)) {
            return "$program is not a folder.";
        }
        if (realpath($program) === $wiki) {
            return "$program is this wiki itself; give the path of the shared program.";
        }
        foreach (self::PROGRAM_FILES as $file) {
            if (!is_readable($program . '/' . $file)) {
                return "$program is not a YesWiki program this user can read: $file is missing or unreadable.";
            }
        }

        $theirs = new ProgramVersion((string)file_get_contents($wiki . '/composer.json'));
        $ours = new ProgramVersion((string)file_get_contents($program . '/composer.json'));
        if ($theirs->releaseLine() !== $ours->releaseLine()) {
            return sprintf(
                'This wiki runs the %s release line and %s is %s; upgrade the wiki first.',
                $theirs->releaseLine() ?: 'doryphore (or older)',
                $program,
                $ours->releaseLine() ?: 'doryphore (or older)'
            );
        }
        if ($theirs->version() !== ProgramVersion::DEV && $ours->version() !== ProgramVersion::DEV
            && version_compare($theirs->version(), $ours->version(), '>')) {
            return "This wiki runs YesWiki {$theirs->version()}, newer than $program ({$ours->version()}); update the program first.";
        }

        return null;
    }

    private function baseUrlOf(string $wiki): string
    {
        $config = (string)@file_get_contents($wiki . '/yeswiki.config.php');

        return preg_match('/[\'"]base_url[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $config, $m) === 1 ? " ({$m[1]})" : '';
    }

    /** @return list<array{0: string, 1: string}> the moves, from => to */
    private function plan(string $wiki, string $program, string $backup): array
    {
        $moves = [];
        foreach (self::OWN_FOLDERS as $folder => $customFolder) {
            foreach ($this->entries($wiki . '/' . $folder) as $name) {
                if (!is_dir($wiki . '/' . $folder . '/' . $name) || file_exists($program . '/' . $folder . '/' . $name)) {
                    continue;
                }
                $target = $wiki . '/' . $customFolder . '/' . $name;
                if (!file_exists($target)) {
                    $moves[] = [$wiki . '/' . $folder . '/' . $name, $target];
                }
            }
        }
        foreach ($this->entries($wiki) as $entry) {
            if ($this->replaced($entry, $program)) {
                $moves[] = [$wiki . '/' . $entry, $backup . '/' . $entry];
            }
        }

        return $moves;
    }

    /** @return list<string> */
    private function unknownEntries(string $wiki, string $program): array
    {
        return array_values(array_filter(
            $this->entries($wiki),
            fn (string $entry): bool => !in_array($entry, self::KEPT, true) && !$this->replaced($entry, $program)
        ));
    }

    /** Whether a top-level entry of the wiki goes to the backup: what the program ships, dotfiles, and build or test tooling. */
    private function replaced(string $entry, string $program): bool
    {
        if (in_array($entry, self::KEPT, true)) {
            return false;
        }

        return str_starts_with($entry, '.')
            || in_array($entry, self::BUILD_AND_TESTS, true)
            || file_exists($program . '/' . $entry);
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
